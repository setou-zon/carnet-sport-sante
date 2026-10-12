<?php

namespace App\Models;

use App\Casts\JsonObject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fiche de présence hebdomadaire d'un site.
 * Les totaux sont toujours recalculés par le serveur à partir des jours.
 */
class Presence extends Model
{
    use HasUuids;

    public const JOURS = ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'];
    public const STATUTS = ['soumis', 'valide', 'a_corriger'];

    protected $fillable = ['site_id', 'annee', 'semaine', 'mois', 'jours', 'observations', 'statut', 'commentaire',
        'saisi_par', 'controle_par', 'controle_le'];

    protected function casts(): array
    {
        return [
            'jours' => JsonObject::class,
            'annee' => 'integer', 'semaine' => 'integer', 'mois' => 'integer',
            'total_f' => 'integer', 'total_h' => 'integer', 'total' => 'integer',
            'controle_le' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** Recalcule total femmes, total hommes et total à partir des jours. */
    public function calculerTotaux(): void
    {
        $f = 0;
        $h = 0;
        $jours = $this->jours;
        foreach (self::JOURS as $d) {
            $j = $jours->{$d} ?? null;
            $f += max(0, (int) ($j->f ?? 0));
            $h += max(0, (int) ($j->h ?? 0));
        }
        $this->total_f = $f;
        $this->total_h = $h;
        $this->total = $f + $h;
    }

    public function scopeVisiblesPar(Builder $q, User $u): Builder
    {
        return $q->whereIn('site_id', Site::query()->visiblesPar($u)->select('id'));
    }
}
