<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Compte utilisateur (évaluateur, promoteur, DPSJE, DRSJE, national, administrateur).
 *
 * Toutes les règles « qui voit quoi » sont regroupées ici, sous forme de méthodes
 * simples, puis utilisées par les Policies et les contrôleurs.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public const NIVEAUX = ['evaluateur', 'promoteur', 'dpsje', 'drsje', 'national'];
    public const ROLES = ['evaluateur', 'admin'];

    protected $fillable = ['nom', 'email', 'password', 'role', 'actif', 'niveau', 'province_id', 'region_id'];
    protected $hidden = ['password', 'remember_token', 'updated_at'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'actif' => 'boolean',
            'province_id' => 'integer',
            'region_id' => 'integer',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Rôles                                                              */
    /* ------------------------------------------------------------------ */

    public function isAdmin(): bool
    {
        return $this->actif && $this->role === 'admin';
    }

    /** Peut consulter et saisir les carnets santé (évaluateur actif ou administrateur). */
    public function isEval(): bool
    {
        return $this->actif && ($this->niveau === 'evaluateur' || $this->role === 'admin');
    }

    /* ------------------------------------------------------------------ */
    /*  Territoire                                                         */
    /* ------------------------------------------------------------------ */

    /** Voit tout le pays (administrateur ou niveau national). */
    public function voitToutLePays(): bool
    {
        return $this->actif && ($this->role === 'admin' || $this->niveau === 'national');
    }

    /**
     * Provinces visibles : null = toutes, [] = aucune.
     *
     * @return int[]|null
     */
    public function provincesVisibles(): ?array
    {
        if (! $this->actif) {
            return [];
        }
        if ($this->voitToutLePays()) {
            return null;
        }
        if ($this->niveau === 'drsje' && $this->region_id) {
            return Province::where('region_id', $this->region_id)->pluck('id')->all();
        }
        if ($this->niveau === 'dpsje' && $this->province_id) {
            return [$this->province_id];
        }

        return [];
    }

    public function canSeeProvince(?int $provinceId): bool
    {
        if ($provinceId === null) {
            return $this->voitToutLePays();
        }
        $visibles = $this->provincesVisibles();

        return $visibles === null || in_array($provinceId, $visibles, true);
    }

    public function canSeeRegion(?int $regionId): bool
    {
        if ($this->voitToutLePays()) {
            return true;
        }

        return $this->actif && $this->niveau === 'drsje' && $regionId !== null && $this->region_id === $regionId;
    }

    /**
     * Rôle de l'utilisateur sur un site :
     *  'promoteur' (son site), 'admin', 'controle' (DPSJE de la province),
     *  'lecture' (DRSJE, national), ou null (aucun accès).
     */
    public function siteRole(?Site $site): ?string
    {
        if (! $site || ! $this->actif) {
            return null;
        }
        if ($site->promoteur_id === $this->id) {
            return 'promoteur';
        }
        if ($this->canSeeProvince($site->province_id)) {
            return match (true) {
                $this->role === 'admin' => 'admin',
                $this->niveau === 'dpsje' => 'controle',
                default => 'lecture',
            };
        }

        return null;
    }

    /** Droit de transmettre un rapport (R-MENS par le promoteur, R-TRIM par la DPSJE, R-SEM par la DRSJE). */
    public function canTransmit(string $type, ?Site $site, ?int $provinceId, ?int $regionId): bool
    {
        if (! $this->actif) {
            return false;
        }

        return match ($type) {
            'R-MENS' => in_array($this->siteRole($site), ['promoteur', 'admin'], true),
            'R-TRIM' => $this->role === 'admin' || ($this->niveau === 'dpsje' && $provinceId !== null && $this->province_id === $provinceId),
            'R-SEM' => $this->role === 'admin' || ($this->niveau === 'drsje' && $regionId !== null && $this->region_id === $regionId),
            default => false,
        };
    }

    /** Représentation renvoyée aux écrans (même forme que l'ancien « profil »). */
    public function toProfil(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'nom' => $this->nom,
            'role' => $this->role,
            'actif' => (bool) $this->actif,
            'niveau' => $this->niveau,
            'province_id' => $this->province_id,
            'region_id' => $this->region_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
