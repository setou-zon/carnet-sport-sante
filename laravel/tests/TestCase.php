<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\TerritoiresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TerritoiresSeeder::class);
    }

    /** Requêtes JSON authentifiées avec le jeton de l'utilisateur. */
    protected function en(User $u): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($u->createToken('test')->plainTextToken);
    }
}
