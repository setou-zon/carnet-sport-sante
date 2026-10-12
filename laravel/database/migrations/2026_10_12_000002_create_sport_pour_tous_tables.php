<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sport pour tous : sites de pratique (F-SITE), fiches de présence
 * hebdomadaires et transmissions de rapports (R-MENS, R-TRIM, R-SEM).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom');
            $table->unsignedSmallInteger('province_id');
            $table->string('localite')->nullable();
            $table->text('activites')->nullable();
            $table->foreignUuid('promoteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('promoteur_nom')->nullable();
            $table->string('profession')->nullable();
            $table->char('sexe', 1)->nullable();          // 'F' | 'H'
            $table->unsignedSmallInteger('age')->nullable();
            $table->string('telephone', 40)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->foreign('province_id')->references('id')->on('provinces');
            $table->index('province_id');
            $table->index('promoteur_id');
        });

        Schema::create('presences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('semaine');       // semaine ISO 1..53
            $table->unsignedTinyInteger('mois');          // 1..12
            $table->json('jours');                        // {lun:{f,h}, mar:{f,h}, ...}
            $table->unsignedInteger('total_f')->default(0);
            $table->unsignedInteger('total_h')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->text('observations')->nullable();
            $table->string('statut', 12)->default('soumis'); // soumis | valide | a_corriger
            $table->text('commentaire')->nullable();
            $table->foreignUuid('saisi_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('controle_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('controle_le')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'annee', 'semaine']);
            $table->index(['site_id', 'annee', 'mois']);
        });

        Schema::create('transmissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 8);                    // R-MENS | R-TRIM | R-SEM
            $table->string('periode', 10);                // AAAA-MM | AAAA-Tn | AAAA-Sn
            $table->foreignUuid('site_id')->nullable()->constrained('sites')->cascadeOnDelete();
            $table->unsignedSmallInteger('province_id')->nullable();
            $table->unsignedSmallInteger('region_id')->nullable();
            // Clé d'unicité calculée (type|periode|site|province|region) : une seule
            // transmission par rapport et par période, quelle que soit la base (MySQL / PostgreSQL).
            $table->string('cle', 120)->unique();
            $table->text('commentaire')->nullable();
            $table->json('synthese');
            $table->foreignUuid('transmis_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transmis_le');

            $table->foreign('province_id')->references('id')->on('provinces');
            $table->foreign('region_id')->references('id')->on('regions');
            $table->index('periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transmissions');
        Schema::dropIfExists('presences');
        Schema::dropIfExists('sites');
    }
};
