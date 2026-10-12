<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Support\Erreur;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sites de pratique Sport pour tous (F-SITE).
 */
class SiteController extends Controller
{
    private function regles(bool $creation): array
    {
        $s = $creation ? 'required' : 'sometimes';

        return [
            'nom' => [$s, 'string', 'max:190'],
            'province_id' => [$s, 'integer', 'exists:provinces,id'],
            'localite' => ['sometimes', 'nullable', 'string', 'max:190'],
            'activites' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'promoteur_nom' => ['sometimes', 'nullable', 'string', 'max:190'],
            'profession' => ['sometimes', 'nullable', 'string', 'max:190'],
            'sexe' => ['sometimes', 'nullable', Rule::in(['F', 'H'])],
            'age' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:120'],
            'telephone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }

    public function index(Request $r)
    {
        return Site::query()->visiblesPar($r->user())->orderBy('nom')->limit(10000)->get();
    }

    public function store(Request $r)
    {
        $u = $r->user();
        $d = $r->validate($this->regles(true));
        if (! $u->can('create', [Site::class, (int) $d['province_id']])) {
            return Erreur::refuse('Province hors de votre territoire');
        }
        $site = new Site($d);
        $site->promoteur_id = $u->id; // la personne qui déclare le site en devient le promoteur
        $site->save();

        return response()->json($site->fresh(), 201);
    }

    public function update(Request $r, Site $site)
    {
        $u = $r->user();
        $d = $r->validate($this->regles(false));
        if (! $u->can('update', [$site, (int) ($d['province_id'] ?? $site->province_id)])) {
            return Erreur::refuse();
        }
        $site->fill($d)->save();

        return response()->json($site->fresh());
    }

    public function destroy(Request $r, Site $site)
    {
        if (! $r->user()->can('delete', $site)) {
            return Erreur::refuse();
        }
        $site->delete();

        return response()->json([['id' => $site->id]]);
    }
}
