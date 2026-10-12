<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\User;
use Tests\TestCase;

class CarnetsTest extends TestCase
{
    private function nouveau(User $u, string $id, array $f = ['nom' => 'Ouédraogo Awa']): \Illuminate\Testing\TestResponse
    {
        return $this->en($u)->postJson('/api/participants', ['id' => $id, 'f' => $f, 'p' => (object) [], 'owner' => 'quelquun-dautre']);
    }

    public function test_code_automatique_et_proprietaire(): void
    {
        $a = User::factory()->create();
        $r = $this->nouveau($a, 'c1')->assertCreated();
        $this->assertMatchesRegularExpression('/^SS-\d{4}-0001$/', $r->json('f.code'));
        $this->assertSame($a->id, $r->json('owner'));
        $this->assertSame('SS-'.now()->format('Y').'-0002', $this->nouveau($a, 'c2')->json('f.code'));
        // un objet vide reste un objet
        $this->assertSame('{}', json_encode($r->json('p') ?: new \stdClass));
        $this->assertStringContainsString('"p":{}', $this->en($a)->getJson('/api/participants')->getContent());
    }

    public function test_doublon_identifiant_renvoie_23505(): void
    {
        $a = User::factory()->create();
        $this->nouveau($a, 'c1')->assertCreated();
        $this->nouveau($a, 'c1')->assertStatus(409)->assertJson(['code' => '23505']);
    }

    public function test_tous_les_evaluateurs_modifient_le_code_ne_change_pas(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $code = $this->nouveau($a, 'c1')->json('f.code');
        $r = $this->en($b)->patchJson('/api/participants/c1', ['f' => ['nom' => 'Modifié', 'code' => 'PIRATE'], 'p' => ['T0' => ['poids' => 70]]])->assertOk();
        $this->assertSame($code, $r->json('f.code'));
        $this->assertSame('Modifié', $r->json('f.nom'));
        $this->assertSame($a->id, $r->json('owner'));
        $this->assertSame($b->id, $r->json('updated_by'));
    }

    public function test_suppression_reservee_a_l_auteur_ou_admin(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $adm = User::factory()->admin()->create();
        $this->nouveau($a, 'c1');
        $this->nouveau($a, 'c2');
        $this->nouveau($b, 'c3');
        $this->en($b)->deleteJson('/api/participants/c1')->assertForbidden();
        $this->en($a)->deleteJson('/api/participants/c1')->assertOk();
        $this->en($b)->postJson('/api/participants/supprimer', ['ids' => ['c2', 'c3']])->assertOk()->assertExactJson([['id' => 'c3']]);
        $this->en($adm)->postJson('/api/participants/supprimer', ['ids' => ['c2']])->assertOk()->assertExactJson([['id' => 'c2']]);
        $this->assertSame(0, Participant::count());
    }

    public function test_promoteurs_directions_et_comptes_inactifs_ne_voient_jamais_les_carnets(): void
    {
        $a = User::factory()->create();
        $this->nouveau($a, 'c1');
        foreach ([
            User::factory()->niveau('promoteur', 32, 5)->create(),
            User::factory()->niveau('dpsje', 32, 5)->create(),
            User::factory()->niveau('drsje', null, 5)->create(),
            User::factory()->niveau('national')->create(),
            User::factory()->inactif()->create(),
        ] as $u) {
            $this->en($u)->getJson('/api/participants')->assertForbidden();
            $this->en($u)->patchJson('/api/participants/c1', ['f' => ['nom' => 'x']])->assertForbidden();
            $this->en($u)->postJson('/api/participants', ['id' => 'z'.$u->id, 'f' => ['nom' => 'x']])->assertForbidden();
        }
        $this->assertSame('Ouédraogo Awa', Participant::find('c1')->f->nom);
    }

    public function test_gestion_des_comptes_par_l_admin(): void
    {
        $adm = User::factory()->admin()->create();
        $e = User::factory()->inactif()->create();
        $this->en($e)->getJson('/api/profiles')->assertOk()->assertJsonCount(1); // inactif : son profil seulement
        $this->en($e)->patchJson("/api/profiles/{$e->id}", ['actif' => true])->assertForbidden();
        $this->en($adm)->patchJson("/api/profiles/{$e->id}", ['actif' => true, 'niveau' => 'dpsje', 'province_id' => 7, 'region_id' => 4])->assertOk()->assertJson(['actif' => true, 'niveau' => 'dpsje']);
        $this->en($adm)->patchJson("/api/profiles/{$adm->id}", ['role' => 'evaluateur'])->assertStatus(400);
        $this->en($adm)->patchJson("/api/profiles/{$adm->id}", ['nom' => 'Nouveau nom'])->assertOk();
        $this->en($adm)->getJson('/api/profiles')->assertOk()->assertJsonCount(2);
        $this->en($adm)->getJson('/api/profiles?id='.$e->id)->assertOk()->assertJsonCount(1)->assertJsonPath('0.province_id', 7);
    }
}
