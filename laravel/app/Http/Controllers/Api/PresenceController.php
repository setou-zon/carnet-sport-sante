<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Presence;
use App\Models\Site;
use App\Models\User;
use App\Support\Erreur;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fiches de présence hebdomadaires.
 *
 * Règles :
 *  - les totaux sont recalculés par le serveur ;
 *  - une fiche saisie ou corrigée par le promoteur repart « soumise » au contrôle ;
 *  - une fiche validée ne peut plus être modifiée par le promoteur ;
 *  - quand la DPSJE change le statut, on note qui a contrôlé et quand.
 */
class PresenceController extends Controller
{
    private function lire(Request $r, bool $creation): array
    {
        $s = $creation ? 'required' : 'sometimes';
        $d = $r->validate([
            'site_id' => [$s, 'uuid'],
            'annee' => [$s, 'integer', 'min:2024', 'max:2100'],
            'semaine' => [$s, 'integer', 'min:1', 'max:53'],
            'mois' => [$s, 'integer', 'min:1', 'max:12'],
            'observations' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'statut' => ['sometimes', Rule::in(Presence::STATUTS)],
            'commentaire' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ]);
        $b = Erreur::corps($r);
        if (property_exists($b, 'jours')) {
            $d['jours'] = is_object($b->jours) ? $b->jours : new \stdClass;
        } elseif ($creation) {
            $d['jours'] = new \stdClass;
        }

        return $d;
    }

    public function index(Request $r)
    {
        $q = Presence::query()->visiblesPar($r->user());
        if ($r->filled('annee_min')) {
            $q->where('annee', '>=', (int) $r->input('annee_min'));
        }
        if ($r->filled('annee_max')) {
            $q->where('annee', '<=', (int) $r->input('annee_max'));
        }

        return $q->limit(50000)->get();
    }

    public function store(Request $r)
    {
        return $this->creer($r->user(), $this->lire($r, true));
    }

    public function update(Request $r, Presence $presence)
    {
        return $this->modifier($r->user(), $presence, $this->lire($r, false));
    }

    /** Enregistre la fiche du site pour la semaine : crée ou met à jour (envoi différé hors ligne). */
    public function upsert(Request $r)
    {
        $d = $this->lire($r, true);
        $existante = Presence::where(['site_id' => $d['site_id'], 'annee' => $d['annee'], 'semaine' => $d['semaine']])->first();

        return $existante ? $this->modifier($r->user(), $existante, $d) : $this->creer($r->user(), $d);
    }

    public function destroy(Request $r, Presence $presence)
    {
        if (! $r->user()->can('delete', $presence)) {
            return Erreur::refuse();
        }
        $presence->delete();

        return response()->json([['id' => $presence->id]]);
    }

    private function creer(User $u, array $d)
    {
        $site = Site::find($d['site_id']);
        if (! $site || ! $u->can('create', [Presence::class, $site])) {
            return Erreur::refuse();
        }
        if (Presence::where(['site_id' => $d['site_id'], 'annee' => $d['annee'], 'semaine' => $d['semaine']])->exists()) {
            return Erreur::doublon('Une fiche existe déjà pour ce site et cette semaine');
        }
        $p = new Presence($d);
        $p->saisi_par = $u->id;
        if ($u->siteRole($site) === 'promoteur') {
            $p->statut = 'soumis';
            $p->commentaire = null;
            $p->controle_par = null;
            $p->controle_le = null;
        } elseif (isset($d['statut']) && $d['statut'] !== 'soumis') {
            $p->controle_par = $u->id;
            $p->controle_le = now();
        }
        $p->statut ??= 'soumis';
        $p->calculerTotaux();
        $p->save();

        return response()->json($p->fresh(), 201);
    }

    private function modifier(User $u, Presence $p, array $d)
    {
        if (! $u->can('update', $p)) {
            return Erreur::refuse();
        }
        unset($d['site_id']); // une fiche ne change jamais de site
        $role = $u->siteRole($p->site);
        $ancienStatut = $p->statut;
        if ($role === 'promoteur') {
            if ($ancienStatut === 'valide') {
                return Erreur::regle('Fiche déjà validée : seule la DPSJE peut la modifier');
            }
            unset($d['statut'], $d['commentaire']);
            $p->fill($d);
            $p->statut = 'soumis'; // le promoteur corrige : la fiche repart au contrôle
        } else {
            $p->fill($d);
            if ($p->statut !== $ancienStatut) {
                $p->controle_par = $u->id;
                $p->controle_le = now();
            }
        }
        if (Presence::where(['site_id' => $p->site_id, 'annee' => $p->annee, 'semaine' => $p->semaine])->whereKeyNot($p->id)->exists()) {
            return Erreur::doublon('Une fiche existe déjà pour ce site et cette semaine');
        }
        $p->calculerTotaux();
        $p->save();

        return response()->json($p->fresh());
    }
}
