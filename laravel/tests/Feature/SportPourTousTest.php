<?php

namespace Tests\Feature;

use App\Models\Presence;
use App\Models\Site;
use App\Models\User;
use Tests\TestCase;

/**
 * Circuit Sport pour tous : Site → DPSJE (province) → DRSJE (région) → SSEC/DST (national).
 * Province 32 = Kadiogo (région 5) ; province 7 = Houet (région 4).
 */
class SportPourTousTest extends TestCase
{
    private User $promo, $promoAutre, $dpsje, $dpsjeAutre, $drsje, $national, $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->promo = User::factory()->niveau('promoteur', 32, 5)->create();
        $this->promoAutre = User::factory()->niveau('promoteur', 7, 4)->create();
        $this->dpsje = User::factory()->niveau('dpsje', 32, 5)->create();
        $this->dpsjeAutre = User::factory()->niveau('dpsje', 7, 4)->create();
        $this->drsje = User::factory()->niveau('drsje', null, 5)->create();
        $this->national = User::factory()->niveau('national')->create();
    }

    private function site(User $u, int $prov = 32): string
    {
        return $this->en($u)->postJson('/api/sites', ['nom' => 'Terrain '.$prov, 'province_id' => $prov, 'sexe' => 'H', 'age' => 41])->assertCreated()->json('id');
    }

    private function jours(int $f, int $h): array
    {
        return collect(Presence::JOURS)->mapWithKeys(fn ($d) => [$d => ['f' => $f, 'h' => $h]])->all();
    }

    public function test_sites_visibles_par_territoire(): void
    {
        $s1 = $this->site($this->promo, 32);
        $s2 = $this->site($this->promoAutre, 7);
        $ids = fn (User $u) => collect($this->en($u)->getJson('/api/sites')->assertOk()->json())->pluck('id')->sort()->values()->all();
        $this->assertSame([$s1], $ids($this->promo));
        $this->assertSame([$s1], $ids($this->dpsje));
        $this->assertSame([$s2], $ids($this->dpsjeAutre));
        $this->assertSame([$s1], $ids($this->drsje));
        $this->assertEqualsCanonicalizing([$s1, $s2], $ids($this->national));
        $this->assertEqualsCanonicalizing([$s1, $s2], $ids($this->admin));
    }

    public function test_dpsje_ne_cree_pas_de_site_hors_de_sa_province(): void
    {
        $this->en($this->dpsje)->postJson('/api/sites', ['nom' => 'X', 'province_id' => 7])->assertForbidden();
        $this->site($this->dpsje, 32);
        $this->en($this->drsje)->postJson('/api/sites', ['nom' => 'X', 'province_id' => 32])->assertForbidden();
    }

    public function test_fiche_totaux_serveur_et_circuit_de_validation(): void
    {
        $s = $this->site($this->promo);
        $r = $this->en($this->promo)->postJson('/api/presences', ['site_id' => $s, 'annee' => 2026, 'semaine' => 41, 'mois' => 10,
            'jours' => $this->jours(2, 3), 'statut' => 'valide', 'total' => 9999])->assertCreated();
        $this->assertSame([14, 21, 35, 'soumis'], [$r->json('total_f'), $r->json('total_h'), $r->json('total'), $r->json('statut')]);
        $id = $r->json('id');

        // doublon de semaine
        $this->en($this->promo)->postJson('/api/presences', ['site_id' => $s, 'annee' => 2026, 'semaine' => 41, 'mois' => 10, 'jours' => []])
            ->assertStatus(409)->assertJson(['code' => '23505']);

        // la DRSJE ne contrôle pas, la DPSJE d'une autre province non plus
        $this->en($this->drsje)->patchJson("/api/presences/$id", ['statut' => 'valide'])->assertForbidden();
        $this->en($this->dpsjeAutre)->patchJson("/api/presences/$id", ['statut' => 'valide'])->assertForbidden();

        // la DPSJE demande une correction, le promoteur corrige : la fiche repart « soumise »
        $c = $this->en($this->dpsje)->patchJson("/api/presences/$id", ['statut' => 'a_corriger', 'commentaire' => 'Samedi ?'])->assertOk();
        $this->assertSame($this->dpsje->id, $c->json('controle_par'));
        $c = $this->en($this->promo)->patchJson("/api/presences/$id", ['jours' => $this->jours(1, 1), 'statut' => 'valide'])->assertOk();
        $this->assertSame(['soumis', 14, 'Samedi ?'], [$c->json('statut'), $c->json('total'), $c->json('commentaire')]);

        // validée : le promoteur ne peut plus modifier ni supprimer
        $this->en($this->dpsje)->patchJson("/api/presences/$id", ['statut' => 'valide'])->assertOk();
        $this->en($this->promo)->patchJson("/api/presences/$id", ['jours' => $this->jours(9, 9)])->assertStatus(400)
            ->assertJsonFragment(['message' => 'Fiche déjà validée : seule la DPSJE peut la modifier']);
        $this->en($this->promo)->deleteJson("/api/presences/$id")->assertForbidden();
        $this->en($this->dpsje)->patchJson("/api/presences/$id", ['jours' => $this->jours(9, 9)])->assertOk()->assertJson(['total' => 126, 'statut' => 'valide']);
    }

    public function test_envoi_differe_upsert_par_semaine(): void
    {
        $s = $this->site($this->promo);
        $a = $this->en($this->promo)->putJson('/api/presences', ['site_id' => $s, 'annee' => 2026, 'semaine' => 40, 'mois' => 10, 'jours' => $this->jours(1, 0)])->assertCreated()->json('id');
        $b = $this->en($this->promo)->putJson('/api/presences', ['site_id' => $s, 'annee' => 2026, 'semaine' => 40, 'mois' => 10, 'jours' => $this->jours(2, 0)])->assertOk();
        $this->assertSame($a, $b->json('id'));
        $this->assertSame(14, $b->json('total'));
        $this->assertSame(1, Presence::count());
    }

    public function test_promoteur_d_un_autre_site_n_a_aucun_acces(): void
    {
        $s = $this->site($this->promo);
        $id = $this->en($this->promo)->postJson('/api/presences', ['site_id' => $s, 'annee' => 2026, 'semaine' => 41, 'mois' => 10, 'jours' => $this->jours(1, 1)])->json('id');
        $this->en($this->promoAutre)->getJson('/api/presences')->assertOk()->assertJsonCount(0);
        $this->en($this->promoAutre)->patchJson("/api/presences/$id", ['jours' => []])->assertForbidden();
        $this->en($this->promoAutre)->postJson('/api/presences', ['site_id' => $s, 'annee' => 2026, 'semaine' => 42, 'mois' => 10, 'jours' => []])->assertForbidden();
        $this->en($this->promoAutre)->patchJson("/api/sites/$s", ['nom' => 'Volé'])->assertForbidden();
        $this->en($this->drsje)->getJson('/api/presences?annee_min=2025&annee_max=2027')->assertOk()->assertJsonCount(1);
    }

    public function test_compte_desactive_perd_tout_acces(): void
    {
        $s = $this->site($this->promo);
        $this->promo->update(['actif' => false]);
        $this->en($this->promo)->getJson('/api/sites')->assertOk()->assertJsonCount(0);
        $this->en($this->promo)->postJson('/api/presences', ['site_id' => $s, 'annee' => 2026, 'semaine' => 41, 'mois' => 10, 'jours' => []])->assertForbidden();
    }

    public function test_transmissions_par_niveau_et_retransmission(): void
    {
        $s = $this->site($this->promo);
        $this->en($this->promo)->putJson('/api/transmissions', ['type' => 'R-MENS', 'periode' => '2026-10', 'site_id' => $s, 'synthese' => ['total' => 35]])->assertCreated();
        $this->en($this->promo)->putJson('/api/transmissions', ['type' => 'R-MENS', 'periode' => '2026-10', 'site_id' => $s, 'synthese' => ['total' => 40], 'commentaire' => 'corrigé'])->assertOk()->assertJsonPath('synthese.total', 40);
        $this->en($this->promo)->putJson('/api/transmissions', ['type' => 'R-TRIM', 'periode' => '2026-T4', 'province_id' => 32])->assertForbidden();
        $this->en($this->dpsjeAutre)->putJson('/api/transmissions', ['type' => 'R-TRIM', 'periode' => '2026-T4', 'province_id' => 32])->assertForbidden();
        $this->en($this->dpsje)->putJson('/api/transmissions', ['type' => 'R-TRIM', 'periode' => '2026-T4', 'province_id' => 32, 'synthese' => []])->assertCreated();
        $this->en($this->drsje)->putJson('/api/transmissions', ['type' => 'R-SEM', 'periode' => '2026-S2', 'region_id' => 5])->assertCreated();
        $this->en($this->drsje)->putJson('/api/transmissions', ['type' => 'R-SEM', 'periode' => '2026-S2', 'region_id' => 4])->assertForbidden();
        $this->en($this->promo)->putJson('/api/transmissions', ['type' => 'R-MENS', 'periode' => '2026-13', 'site_id' => $s])->assertUnprocessable();

        $n = fn (User $u) => count($this->en($u)->getJson('/api/transmissions?annee=2026')->assertOk()->json());
        $this->assertSame(1, $n($this->promo));
        $this->assertSame(2, $n($this->dpsje));     // R-MENS de sa province + son R-TRIM
        $this->assertSame(0, $n($this->dpsjeAutre));
        $this->assertSame(3, $n($this->drsje));
        $this->assertSame(3, $n($this->national));
        $this->assertSame(0, count($this->en($this->national)->getJson('/api/transmissions?annee=2025')->json()));
    }

    public function test_suppression_de_site_reservee_a_la_dpsje_et_admin(): void
    {
        $s = $this->site($this->promo);
        $this->en($this->promo)->deleteJson("/api/sites/$s")->assertForbidden();
        $this->en($this->drsje)->deleteJson("/api/sites/$s")->assertForbidden();
        $this->en($this->dpsje)->deleteJson("/api/sites/$s")->assertOk();
        $this->assertSame(0, Site::count());
    }
}
