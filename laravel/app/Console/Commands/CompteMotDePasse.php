<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CompteMotDePasse extends Command
{
    protected $signature = 'compte:mot-de-passe {email}';
    protected $description = 'Crée un mot de passe provisoire pour un compte (et déconnecte ses appareils)';

    public function handle(): int
    {
        $u = User::where('email', Str::lower(trim($this->argument('email'))))->first();
        if (! $u) {
            $this->error('Aucun compte avec cet email.');

            return self::FAILURE;
        }
        $mdp = Str::password(12, symbols: false);
        $u->forceFill(['password' => $mdp])->save();
        $u->tokens()->delete();
        $this->info("Mot de passe provisoire pour {$u->email} : {$mdp}");
        $this->line('Transmettez-le à la personne de vive voix ; elle pourra le changer avec « Mot de passe oublié ? ».');

        return self::SUCCESS;
    }
}
