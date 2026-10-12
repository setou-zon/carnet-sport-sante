<?php

namespace App\Models;

use App\Casts\JsonObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Carnet Sport Santé d'un participant.
 */
class Participant extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'owner', 'f', 'p', 'updated_by'];

    protected function casts(): array
    {
        return ['f' => JsonObject::class, 'p' => JsonObject::class];
    }

    /**
     * Prochain code participant : SS-AAAA-NNNN (numérotation continue, sans doublon,
     * même avec plusieurs évaluateurs qui enregistrent en même temps).
     */
    public static function prochainCode(): string
    {
        $n = DB::transaction(function () {
            $row = DB::table('compteurs')->where('nom', 'participant_code')->lockForUpdate()->first();
            if (! $row) {
                DB::table('compteurs')->insert(['nom' => 'participant_code', 'valeur' => 1]);

                return 1;
            }
            DB::table('compteurs')->where('nom', 'participant_code')->update(['valeur' => $row->valeur + 1]);

            return $row->valeur + 1;
        });
        $annee = now('Africa/Ouagadougou')->format('Y');

        return sprintf('SS-%s-%04d', $annee, $n);
    }
}
