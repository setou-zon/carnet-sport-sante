<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Réponses d'erreur au format attendu par l'application.
 * `code` reprend les codes de l'ancienne base pour que les écrans
 * affichent le bon message (23505 = doublon, 42501 = accès refusé).
 */
class Erreur
{
    public static function doublon(string $message = 'Cet enregistrement existe déjà'): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => '23505'], 409);
    }

    public static function refuse(string $message = 'Accès refusé'): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => '42501'], 403);
    }

    public static function regle(string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => 'P0001'], 400);
    }

    /** Corps JSON de la requête décodé en objets (préserve `{}`). */
    public static function corps(\Illuminate\Http\Request $r): object
    {
        $o = json_decode($r->getContent());

        return is_object($o) ? $o : new \stdClass;
    }
}
