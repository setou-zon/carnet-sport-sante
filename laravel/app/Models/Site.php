<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Site de pratique Sport pour tous (F-SITE).
 */
class Site extends Model
{
    use HasUuids;

    protected $fillable = ['nom', 'province_id', 'localite', 'activites', 'promoteur_id', 'promoteur_nom',
        'profession', 'sexe', 'age', 'telephone', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'province_id' => 'integer', 'age' => 'integer'];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** Sites visibles par l'utilisateur : les siens, et ceux de son territoire. */
    public function scopeVisiblesPar(Builder $q, User $u): Builder
    {
        if (! $u->actif) {
            return $q->whereRaw('1 = 0');
        }
        $prov = $u->provincesVisibles();
        if ($prov === null) {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('promoteur_id', $u->id)->orWhereIn('province_id', $prov ?: [-1]));
    }
}
