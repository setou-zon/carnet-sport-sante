<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_le_premier_compte_devient_administrateur_actif(): void
    {
        $r = $this->postJson('/api/auth/register', ['email' => 'Admin@Sport.bf', 'password' => 'secret123', 'nom' => 'Admin']);
        $r->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'email']]);
        $u = User::first();
        $this->assertSame('admin', $u->role);
        $this->assertTrue($u->actif);
        $this->assertSame('admin@sport.bf', $u->email);
    }

    public function test_les_comptes_suivants_sont_en_attente_avec_leur_territoire(): void
    {
        User::factory()->admin()->create();
        $this->postJson('/api/auth/register', ['email' => 'p@x.bf', 'password' => 'secret123', 'nom' => 'P', 'niveau' => 'promoteur', 'province_id' => '32'])->assertCreated();
        $u = User::where('email', 'p@x.bf')->first();
        $this->assertFalse($u->actif);
        $this->assertSame('evaluateur', $u->role);
        $this->assertSame('promoteur', $u->niveau);
        $this->assertSame(32, $u->province_id);
        $this->assertSame(5, $u->region_id); // région déduite de la province (Kadiogo)
    }

    public function test_niveau_inconnu_ramene_a_evaluateur_et_email_en_double_refuse(): void
    {
        User::factory()->admin()->create();
        $this->postJson('/api/auth/register', ['email' => 'e@x.bf', 'password' => 'secret123', 'niveau' => 'roi'])->assertCreated();
        $this->assertSame('evaluateur', User::where('email', 'e@x.bf')->value('niveau'));
        $this->postJson('/api/auth/register', ['email' => 'e@x.bf', 'password' => 'secret123'])
            ->assertStatus(422)->assertJson(['message' => 'User already registered']);
    }

    public function test_connexion_et_mauvais_mot_de_passe(): void
    {
        User::factory()->create(['email' => 'a@x.bf']);
        $this->postJson('/api/auth/login', ['email' => 'a@x.bf', 'password' => 'faux'])->assertStatus(400)->assertJson(['message' => 'Invalid login credentials']);
        $t = $this->postJson('/api/auth/login', ['email' => 'A@x.bf ', 'password' => 'secret123'])->assertOk()->json('token');
        $this->withToken($t)->getJson('/api/auth/me')->assertOk()->assertJson(['email' => 'a@x.bf']);
    }

    public function test_ancien_mot_de_passe_supabase_bcrypt_2a_accepte(): void
    {
        // Les mots de passe importés de Supabase sont des bcrypt « $2a$ ».
        $hash = str_replace('$2y$', '$2a$', password_hash('ancien-mdp', PASSWORD_BCRYPT, ['cost' => 4]));
        $u = User::factory()->create(['email' => 'old@x.bf']);
        \DB::table('users')->where('id', $u->id)->update(['password' => $hash]);
        $this->postJson('/api/auth/login', ['email' => 'old@x.bf', 'password' => 'faux'])->assertStatus(400);
        $this->postJson('/api/auth/login', ['email' => 'old@x.bf', 'password' => 'ancien-mdp'])->assertOk();
        $this->assertStringStartsWith('$2y$', \DB::table('users')->where('id', $u->id)->value('password'));
        $this->postJson('/api/auth/login', ['email' => 'old@x.bf', 'password' => 'ancien-mdp'])->assertOk();
    }

    public function test_deconnexion_revoque_le_jeton(): void
    {
        $u = User::factory()->create();
        $t = $u->createToken('x')->plainTextToken;
        $this->withToken($t)->postJson('/api/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($t)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_mot_de_passe_oublie_puis_reinitialisation(): void
    {
        Notification::fake();
        $u = User::factory()->create(['email' => 'r@x.bf']);
        $this->postJson('/api/auth/forgot-password', ['email' => 'r@x.bf'])->assertOk();
        $this->postJson('/api/auth/forgot-password', ['email' => 'inconnu@x.bf'])->assertOk(); // même réponse
        $token = null;
        Notification::assertSentTo($u, ResetPassword::class, function ($n) use (&$token, $u) {
            $token = $n->token;
            $mail = $n->toMail($u);
            $this->assertStringContainsString('/?reset='.$token.'&email=r%40x.bf', $mail->actionUrl);
            return true;
        });
        $this->postJson('/api/auth/reset-password', ['token' => 'faux', 'email' => 'r@x.bf', 'password' => 'nouveau1'])->assertStatus(400);
        $this->postJson('/api/auth/reset-password', ['token' => $token, 'email' => 'r@x.bf', 'password' => 'nouveau1'])->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/api/auth/login', ['email' => 'r@x.bf', 'password' => 'nouveau1'])->assertOk();
        $this->postJson('/api/auth/reset-password', ['token' => $token, 'email' => 'r@x.bf', 'password' => 'autre123'])->assertStatus(400); // lien à usage unique
    }

    public function test_route_protegee_sans_jeton(): void
    {
        $this->getJson('/api/participants')->assertUnauthorized();
        $this->getJson('/api/regions')->assertOk()->assertJsonCount(17);
        $this->getJson('/api/provinces')->assertOk()->assertJsonCount(47);
    }

    public function test_limites_par_email_et_non_partagees_entre_actions(): void
    {
        User::factory()->admin()->create();
        // 30 personnes qui créent leur compte depuis le même bureau : aucune n'est bloquée
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/auth/register', ['email' => "agent$i@x.bf", 'password' => 'secret123'])->assertCreated();
        }
        // ... et le mot de passe oublié fonctionne encore juste après
        $this->postJson('/api/auth/forgot-password', ['email' => 'agent1@x.bf'])->assertOk();
        // 10 essais ratés sur un compte bloquent ce compte, pas les autres
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'agent2@x.bf', 'password' => 'faux'])->assertStatus(400);
        }
        $this->postJson('/api/auth/login', ['email' => 'agent2@x.bf', 'password' => 'secret123'])->assertStatus(429);
        $this->postJson('/api/auth/login', ['email' => 'agent3@x.bf', 'password' => 'secret123'])->assertOk();
    }
}
