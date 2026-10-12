<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Outils de dépannage des comptes, depuis le serveur.
 *
 *   php artisan compte:admin agent@exemple.bf          → donne le rôle administrateur et active le compte
 *   php artisan compte:mot-de-passe agent@exemple.bf   → crée un mot de passe provisoire et l'affiche
 */
class CompteAdmin extends Command
{
    protected $signature = 'compte:admin {email}';
    protected $description = 'Donne le rôle administrateur à un compte et l\'active';

    public function handle(): int
    {
        $u = User::where('email', Str::lower(trim($this->argument('email'))))->first();
        if (! $u) {
            $this->error('Aucun compte avec cet email.');

            return self::FAILURE;
        }
        $u->forceFill(['role' => 'admin', 'actif' => true])->save();
        $this->info("{$u->email} est maintenant administrateur (compte actif).");

        return self::SUCCESS;
    }
}
