<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\User;
use App\Support\Erreur;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Profils (comptes) vus par l'application et gestion des comptes par l'administrateur.
 */
class ProfilController extends Controller
{
    public function index(Request $r)
    {
        $u = $r->user();
        $q = User::query()->orderBy('created_at');
        if ($r->filled('id')) {
            $q->whereKey($r->input('id'));
        }
        if (! $u->isEval()) {
            $q->whereKey($u->id); // chacun voit au moins son propre profil
        }

        return $q->get()->map->toProfil()->values();
    }

    public function update(Request $r, User $profil)
    {
        $u = $r->user();
        if (! $u->can('update', $profil)) {
            return Erreur::refuse();
        }
        $d = $r->validate([
            'nom' => ['sometimes', 'nullable', 'string', 'max:190'],
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'actif' => ['sometimes', 'boolean'],
            'niveau' => ['sometimes', Rule::in(User::NIVEAUX)],
            'province_id' => ['sometimes', 'nullable', 'integer', 'exists:provinces,id'],
            'region_id' => ['sometimes', 'nullable', 'integer', 'exists:regions,id'],
        ]);
        // On ne peut pas modifier son propre rôle ni se désactiver soi-même.
        if ($profil->id === $u->id && ((isset($d['role']) && $d['role'] !== $profil->role) || (isset($d['actif']) && (bool) $d['actif'] !== $profil->actif))) {
            return Erreur::regle('Vous ne pouvez pas modifier votre propre rôle');
        }
        $profil->fill($d)->save();

        return response()->json($profil->toProfil());
    }
}
