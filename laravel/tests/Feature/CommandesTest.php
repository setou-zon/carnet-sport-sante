<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class CommandesTest extends TestCase
{
    public function test_compte_admin_et_mot_de_passe_provisoire(): void
    {
        $u = User::factory()->inactif()->create(['email' => 'agent@x.bf']);
        $this->artisan('compte:admin', ['email' => 'Agent@x.bf'])->assertSuccessful();
        $this->assertTrue($u->fresh()->isAdmin());
        $this->artisan('compte:admin', ['email' => 'personne@x.bf'])->assertFailed();

        $ancien = $u->createToken('x')->plainTextToken;
        $this->artisan('compte:mot-de-passe', ['email' => 'agent@x.bf'])->assertSuccessful();
        $this->assertSame(0, $u->tokens()->count());
        $this->postJson('/api/auth/login', ['email' => 'agent@x.bf', 'password' => 'secret123'])->assertStatus(400);
    }
}
