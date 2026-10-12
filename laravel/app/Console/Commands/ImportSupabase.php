<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Importe l'export JSON de l'ancienne base Supabase (voir database/supabase/export.sql).
 *
 *   php artisan import:supabase export.json
 *
 * - Les identifiants sont conservés (les appareils déjà utilisés retrouvent leurs carnets).
 * - Les comptes gardent leur mot de passe actuel.
 * - La commande peut être relancée : les lignes existantes sont mises à jour, rien n'est dupliqué.
 *   On peut donc faire un essai, puis un import final le jour de la bascule.
 */
class ImportSupabase extends Command
{
    protected $signature = 'import:supabase {fichier : chemin du fichier export.json} {--essai : vérifier sans rien enregistrer}';
    protected $description = "Importe les comptes, carnets et données Sport pour tous depuis l'export Supabase";

    public function handle(): int
    {
        $chemin = $this->argument('fichier');
        if (! is_readable($chemin)) {
            $this->error("Fichier introuvable : $chemin");

            return self::FAILURE;
        }
        $brut = trim(file_get_contents($chemin));
        $e = json_decode($brut);
        // L'éditeur SQL peut renvoyer [{"export": {...}}] ou {"export": {...}}
        if (is_array($e)) {
            $e = $e[0] ?? null;
        }
        if (is_object($e) && isset($e->export)) {
            $e = is_string($e->export) ? json_decode($e->export) : $e->export;
        }
        if (! is_object($e) || ! isset($e->users)) {
            $this->error("Ce fichier n'est pas un export valide (clé « users » absente).");

            return self::FAILURE;
        }

        $date = fn ($v) => $v ? Carbon::parse($v)->utc()->format('Y-m-d H:i:s') : null;
        $json = fn ($v) => json_encode(is_object($v) ? $v : (object) [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = fn (?string $h) => $h && (str_starts_with($h, '$2a$') || str_starts_with($h, '$2b$')) ? '$2y$'.substr($h, 4) : ($h ?: bcrypt(bin2hex(random_bytes(16))));

        $users = array_map(fn ($u) => [
            'id' => $u->id, 'email' => strtolower(trim($u->email)), 'password' => $hash($u->password ?? null),
            'nom' => $u->nom ?? null, 'role' => in_array($u->role ?? '', ['admin', 'evaluateur'], true) ? $u->role : 'evaluateur',
            'actif' => (bool) ($u->actif ?? false), 'niveau' => $u->niveau ?? 'evaluateur',
            'province_id' => $u->province_id ?? null, 'region_id' => $u->region_id ?? null,
            'created_at' => $date($u->created_at ?? null), 'updated_at' => now()->format('Y-m-d H:i:s'),
        ], $e->users);

        $participants = array_map(fn ($p) => [
            'id' => $p->id, 'owner' => $p->owner, 'f' => $json($p->f ?? null), 'p' => $json($p->p ?? null),
            'updated_by' => $p->updated_by ?? null, 'created_at' => $date($p->created_at ?? null), 'updated_at' => $date($p->updated_at ?? null),
        ], $e->participants ?? []);

        $champsSite = ['id', 'nom', 'province_id', 'localite', 'activites', 'promoteur_id', 'promoteur_nom', 'profession', 'sexe', 'age', 'telephone', 'actif'];
        $sites = array_map(function ($s) use ($champsSite, $date) {
            $l = [];
            foreach ($champsSite as $c) {
                $l[$c] = $s->$c ?? null;
            }
            $l['actif'] = (bool) ($s->actif ?? true);

            return $l + ['created_at' => $date($s->created_at ?? null), 'updated_at' => $date($s->updated_at ?? null)];
        }, $e->sites ?? []);

        $presences = array_map(fn ($p) => [
            'id' => $p->id, 'site_id' => $p->site_id, 'annee' => $p->annee, 'semaine' => $p->semaine, 'mois' => $p->mois,
            'jours' => $json($p->jours ?? null), 'total_f' => $p->total_f ?? 0, 'total_h' => $p->total_h ?? 0, 'total' => $p->total ?? 0,
            'observations' => $p->observations ?? null, 'statut' => $p->statut ?? 'soumis', 'commentaire' => $p->commentaire ?? null,
            'saisi_par' => $p->saisi_par ?? null, 'controle_par' => $p->controle_par ?? null, 'controle_le' => $date($p->controle_le ?? null),
            'created_at' => $date($p->created_at ?? null), 'updated_at' => $date($p->updated_at ?? null),
        ], $e->presences ?? []);

        $transmissions = array_map(fn ($t) => [
            'id' => $t->id, 'type' => $t->type, 'periode' => $t->periode, 'site_id' => $t->site_id ?? null,
            'province_id' => $t->province_id ?? null, 'region_id' => $t->region_id ?? null,
            'cle' => \App\Models\Transmission::cle($t->type, $t->periode, $t->site_id ?? null, $t->province_id ?? null, $t->region_id ?? null),
            'commentaire' => $t->commentaire ?? null, 'synthese' => $json($t->synthese ?? null),
            'transmis_par' => $t->transmis_par ?? null, 'transmis_le' => $date($t->transmis_le ?? null) ?? now()->format('Y-m-d H:i:s'),
        ], $e->transmissions ?? []);

        // Numérotation des codes : on repart après le plus grand numéro déjà attribué.
        $max = (int) ($e->compteur ?? 0);
        foreach ($e->participants ?? [] as $p) {
            if (preg_match('/^SS-\d{4}-(\d+)$/', (string) ($p->f->code ?? ''), $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        $this->table(['Données', 'Nombre'], [
            ['Comptes', count($users)], ['Carnets', count($participants)], ['Sites', count($sites)],
            ['Fiches de présence', count($presences)], ['Transmissions', count($transmissions)], ['Dernier n° de code', $max],
        ]);
        if ($this->option('essai')) {
            $this->info('Essai : rien n\'a été enregistré.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($users, $participants, $sites, $presences, $transmissions, $max) {
            $maj = fn (array $l) => array_values(array_diff(array_keys($l[0] ?? []), ['id', 'created_at']));
            foreach (array_chunk($users, 200) as $c) {
                DB::table('users')->upsert($c, ['id'], $maj($c));
            }
            foreach (array_chunk($participants, 200) as $c) {
                DB::table('participants')->upsert($c, ['id'], $maj($c));
            }
            foreach (array_chunk($sites, 200) as $c) {
                DB::table('sites')->upsert($c, ['id'], $maj($c));
            }
            foreach (array_chunk($presences, 500) as $c) {
                DB::table('presences')->upsert($c, ['id'], $maj($c));
            }
            foreach (array_chunk($transmissions, 500) as $c) {
                DB::table('transmissions')->upsert($c, ['id'], $maj($c));
            }
            $actuel = (int) DB::table('compteurs')->where('nom', 'participant_code')->value('valeur');
            DB::table('compteurs')->upsert([['nom' => 'participant_code', 'valeur' => max($actuel, $max)]], ['nom'], ['valeur']);
        });

        $this->info('Import terminé. Les utilisateurs se connectent avec leur email et leur mot de passe habituels.');

        return self::SUCCESS;
    }
}
