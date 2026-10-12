<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Presence;
use App\Models\User;
use Tests\TestCase;

class ImportSupabaseTest extends TestCase
{
    private string $fichier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fichier = base_path('tests/Fixtures/export-exemple.json');
    }

    public function test_essai_n_enregistre_rien(): void
    {
        $this->artisan('import:supabase', ['fichier' => $this->fichier, '--essai' => true])->assertSuccessful();
        $this->assertSame(0, User::count());
    }

    public function test_import_complet_puis_relance_sans_doublon(): void
    {
        $this->artisan('import:supabase', ['fichier' => $this->fichier])->assertSuccessful();
        $this->artisan('import:supabase', ['fichier' => $this->fichier])->assertSuccessful(); // relance : aucun doublon

        $this->assertSame(3, User::count());
        $this->assertSame(2, Participant::count());
        $this->assertSame('admin@msje.bf', User::find('11111111-1111-4111-8111-111111111111')->email);

        // les utilisateurs gardent leur mot de passe
        $t = $this->postJson('/api/auth/login', ['email' => 'eval@msje.bf', 'password' => 'ancien-mdp'])->assertOk()->json('token');

        // carnets : JSON intact, objets vides conservés
        $r = $this->withToken($t)->getJson('/api/participants')->assertOk();
        $this->assertStringContainsString('"M6":{}', $r->getContent());
        $this->assertStringContainsString('"p":{}', $r->getContent());

        // la numérotation repart après le plus grand code (0005 ; compteur Supabase 6 → prochain 0007)
        $code = $this->withToken($t)->postJson('/api/participants', ['id' => 'nouveau', 'f' => ['nom' => 'X'], 'p' => []])->json('f.code');
        $this->assertSame('SS-'.now()->format('Y').'-0007', $code);

        // Sport pour tous : le promoteur retrouve son site, sa fiche validée et sa transmission
        $this->app['auth']->forgetGuards();
        $tp = $this->postJson('/api/auth/login', ['email' => 'promo@msje.bf', 'password' => 'ancien-mdp'])->json('token');
        $this->withToken($tp)->getJson('/api/sites')->assertJsonCount(1);
        $this->withToken($tp)->getJson('/api/presences')->assertJsonCount(1)->assertJsonPath('0.statut', 'valide');
        $this->withToken($tp)->getJson('/api/transmissions?annee=2026')->assertJsonCount(1);
        $this->withToken($tp)->patchJson('/api/presences/55555555-5555-4555-8555-555555555555', ['jours' => []])->assertStatus(400);
        $this->assertSame(5, Presence::first()->total);
    }

    public function test_fichier_invalide(): void
    {
        $f = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($f, '{"autre":1}');
        $this->artisan('import:supabase', ['fichier' => $f])->assertFailed();
        $this->artisan('import:supabase', ['fichier' => '/nexiste/pas.json'])->assertFailed();
    }
}
