<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** Liste des comptes : évaluateurs et administrateur (pour afficher les auteurs des carnets). */
    public function viewAny(User $u): bool { return $u->isEval(); }

    /** Seul l'administrateur modifie les comptes. */
    public function update(User $u, User $cible): bool { return $u->isAdmin(); }
}
