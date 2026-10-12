<?php

namespace App\Models;

use App\Casts\JsonObject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Transmission d'un rapport : R-MENS (site → DPSJE), R-TRIM (province → DRSJE),
 * R-SEM (région → SSEC/DST).
 */
class Transmission extends Model
{
    use HasUuids;

    public const TYPES = ['R-MENS', 'R-TRIM', 'R-SEM'];
    public $timestamps = false;
    protected $fillable = ['type', 'periode', 'site_id', 'province_id', 'region_id', 'commentaire', 'synthese',
        'transmis_par', 'transmis_le'];
    protected $hidden = ['cle'];

    protected function casts(): array
    {
        return ['synthese' => JsonObject::class, 'province_id' => 'integer', 'region_id' => 'integer', 'transmis_le' => 'datetime'];
    }

    public static function cle(string $type, string $periode, ?string $site, ?int $prov, ?int $reg): string
    {
        return implode('|', [$type, $periode, $site ?? '-', $prov ?? '-', $reg ?? '-']);
    }

    public function scopeVisiblesPar(Builder $q, User $u): Builder
    {
        if (! $u->actif) {
            return $q->whereRaw('1 = 0');
        }
        if ($u->voitToutLePays()) {
            return $q;
        }
        $prov = $u->provincesVisibles() ?: [-1];
        $sites = Site::query()->visiblesPar($u)->select('id');

        return $q->where(function ($w) use ($u, $prov, $sites) {
            $w->where(fn ($x) => $x->where('type', 'R-MENS')->whereIn('site_id', $sites))
                ->orWhere(fn ($x) => $x->where('type', 'R-TRIM')->whereIn('province_id', $prov))
                ->orWhere(fn ($x) => $x->where('type', 'R-SEM')->where('region_id', $u->niveau === 'drsje' ? $u->region_id : -1));
        });
    }
}
