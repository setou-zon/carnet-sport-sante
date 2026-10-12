<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Transmission;
use App\Support\Erreur;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Transmission des rapports dans le circuit Site → DPSJE → DRSJE → SSEC/DST.
 * Retransmettre un rapport pour la même période le remplace.
 */
class TransmissionController extends Controller
{
    public function index(Request $r)
    {
        $q = Transmission::query()->visiblesPar($r->user());
        if ($r->filled('annee')) {
            $q->where('periode', 'like', ((int) $r->input('annee')).'-%');
        }

        return $q->limit(10000)->get();
    }

    public function upsert(Request $r)
    {
        $u = $r->user();
        $d = $r->validate([
            'type' => ['required', Rule::in(Transmission::TYPES)],
            'periode' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2]|T[1-4]|S[12])$/'],
            'site_id' => ['nullable', 'uuid'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'commentaire' => ['nullable', 'string', 'max:4000'],
            'transmis_le' => ['nullable', 'date'],
        ]);
        $b = Erreur::corps($r);
        $site = isset($d['site_id']) ? Site::find($d['site_id']) : null;
        $pv = $d['province_id'] ?? null;
        $rg = $d['region_id'] ?? null;
        if (! $u->canTransmit($d['type'], $site, $pv, $rg)) {
            return Erreur::refuse();
        }
        $cle = Transmission::cle($d['type'], $d['periode'], $d['site_id'] ?? null, $pv, $rg);
        $t = Transmission::firstOrNew(['cle' => $cle]);
        $t->fill([
            'type' => $d['type'], 'periode' => $d['periode'],
            'site_id' => $d['site_id'] ?? null, 'province_id' => $pv, 'region_id' => $rg,
            'commentaire' => $d['commentaire'] ?? null,
            'synthese' => is_object($b->synthese ?? null) ? $b->synthese : new \stdClass,
            'transmis_par' => $u->id,
            'transmis_le' => isset($d['transmis_le']) ? Carbon::parse($d['transmis_le']) : now(),
        ]);
        $t->cle = $cle;
        $t->save();

        return response()->json($t->fresh(), $t->wasRecentlyCreated ? 201 : 200);
    }
}
