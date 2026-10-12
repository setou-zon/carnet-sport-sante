<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    /** Promoteur : crée son propre site. DPSJE ou admin : crée un site dans son territoire. */
    public function create(User $u, ?int $provinceId): bool
    {
        if (! $u->actif) {
            return false;
        }
        if ($u->niveau === 'promoteur') {
            return true;
        }

        return ($u->niveau === 'dpsje' || $u->isAdmin()) && $u->canSeeProvince($provinceId);
    }

    /** Modifier : le promoteur du site, la DPSJE de la province ou l'admin (et la nouvelle province doit rester visible). */
    public function update(User $u, Site $s, ?int $nouvelleProvince): bool
    {
        if (! in_array($u->siteRole($s), ['promoteur', 'controle', 'admin'], true)) {
            return false;
        }

        return $s->promoteur_id === $u->id || $u->canSeeProvince($nouvelleProvince);
    }

    public function delete(User $u, Site $s): bool
    {
        return in_array($u->siteRole($s), ['controle', 'admin'], true);
    }
}
