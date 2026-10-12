<?php

namespace App\Policies;

use App\Models\Presence;
use App\Models\Site;
use App\Models\User;

class PresencePolicy
{
    /** Saisir une fiche : le promoteur du site, la DPSJE ou l'admin. DRSJE et national : lecture seule. */
    public function create(User $u, Site $site): bool
    {
        return in_array($u->siteRole($site), ['promoteur', 'controle', 'admin'], true);
    }

    public function update(User $u, Presence $p): bool
    {
        return in_array($u->siteRole($p->site), ['promoteur', 'controle', 'admin'], true);
    }

    /** Supprimer : DPSJE ou admin ; le promoteur seulement tant que la fiche n'est pas validée. */
    public function delete(User $u, Presence $p): bool
    {
        $r = $u->siteRole($p->site);

        return in_array($r, ['controle', 'admin'], true) || ($r === 'promoteur' && $p->statut !== 'valide');
    }
}
