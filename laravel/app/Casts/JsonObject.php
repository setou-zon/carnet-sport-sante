<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Colonne JSON lue en objets (stdClass) et non en tableaux PHP.
 *
 * Indispensable pour l'application : un objet vide doit rester `{}` en JSON
 * (et non devenir `[]`), sinon les écrans perdent les données qu'ils y ajoutent.
 */
class JsonObject implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return new \stdClass;
        }
        $decoded = json_decode($value);

        return is_object($decoded) ? $decoded : new \stdClass;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_string($value)) {
            return $value;
        }
        if ($value === null || $value === [] ) {
            return '{}';
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
