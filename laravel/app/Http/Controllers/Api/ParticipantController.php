<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Support\Erreur;
use Illuminate\Http\Request;

/**
 * Carnets Sport Santé.
 */
class ParticipantController extends Controller
{
    public function index(Request $r)
    {
        if (! $r->user()->can('viewAny', Participant::class)) {
            return Erreur::refuse();
        }

        return Participant::orderByDesc('updated_at')->limit(5000)->get();
    }

    /** Nouveau carnet. L'identifiant vient de l'appareil (saisie hors ligne possible). */
    public function store(Request $r)
    {
        $u = $r->user();
        if (! $u->can('create', Participant::class)) {
            return Erreur::refuse();
        }
        $b = Erreur::corps($r);
        $id = is_string($b->id ?? null) ? trim($b->id) : '';
        if ($id === '' || strlen($id) > 64) {
            return response()->json(['message' => 'Identifiant manquant', 'code' => '22023'], 422);
        }
        if (Participant::whereKey($id)->exists()) {
            return Erreur::doublon();
        }
        $f = is_object($b->f ?? null) ? $b->f : new \stdClass;
        if (trim((string) ($f->code ?? '')) === '') {
            $f->code = Participant::prochainCode();
        }
        $p = Participant::create([
            'id' => $id,
            'owner' => $u->id,          // l'auteur est toujours la personne connectée
            'f' => $f,
            'p' => is_object($b->p ?? null) ? $b->p : new \stdClass,
            'updated_by' => $u->id,
        ]);

        return response()->json($p->fresh(), 201);
    }

    public function update(Request $r, Participant $participant)
    {
        $u = $r->user();
        if (! $u->can('update', $participant)) {
            return Erreur::refuse();
        }
        $b = Erreur::corps($r);
        $codeExistant = trim((string) ($participant->f->code ?? ''));
        if (property_exists($b, 'f') && is_object($b->f)) {
            $f = $b->f;
            // Le code attribué ne change jamais.
            $f->code = $codeExistant !== '' ? $codeExistant : (trim((string) ($f->code ?? '')) ?: Participant::prochainCode());
            $participant->f = $f;
        }
        if (property_exists($b, 'p') && is_object($b->p)) {
            $participant->p = $b->p;
        }
        $participant->updated_by = $u->id;
        $participant->updated_at = now();
        $participant->save();

        return response()->json($participant->fresh());
    }

    public function destroy(Request $r, Participant $participant)
    {
        if (! $r->user()->can('delete', $participant)) {
            return Erreur::refuse();
        }
        $participant->delete();

        return response()->json([['id' => $participant->id]]);
    }

    /** Suppression groupée (administrateur ou auteur) : renvoie les carnets réellement supprimés. */
    public function destroyMany(Request $r)
    {
        $ids = array_values(array_filter((array) $r->input('ids', []), 'is_string'));
        $u = $r->user();
        $sup = [];
        foreach (Participant::whereKey($ids)->get() as $p) {
            if ($u->can('delete', $p)) {
                $p->delete();
                $sup[] = ['id' => $p->id];
            }
        }

        return response()->json($sup);
    }
}
