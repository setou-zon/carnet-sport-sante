<?php

namespace App\Policies;

use App\Models\Participant;
use App\Models\User;

/**
 * Carnets santé : réservés aux évaluateurs actifs et à l'administrateur.
 * Tous les évaluateurs voient et modifient tous les carnets ;
 * seul l'auteur du carnet ou l'administrateur peut le supprimer.
 * Les promoteurs et les directions (DPSJE, DRSJE, national) n'y ont jamais accès.
 */
class ParticipantPolicy
{
    public function viewAny(User $u): bool { return $u->isEval(); }
    public function create(User $u): bool { return $u->isEval(); }
    public function update(User $u, Participant $p): bool { return $u->isEval(); }
    public function delete(User $u, Participant $p): bool
    {
        return $u->isEval() && ($p->owner === $u->id || $u->isAdmin());
    }
}
