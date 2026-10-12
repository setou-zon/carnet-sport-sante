<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carnet Sport Santé.
 *
 * participants.id est fourni par l'application (identifiant créé sur l'appareil,
 * pour permettre la saisie hors ligne). f = fiche du participant (JSON, contient
 * le code SS-AAAA-NNNN), p = résultats des évaluations T0 / 6 mois / 12 mois (JSON).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->foreignUuid('owner')->constrained('users')->restrictOnDelete();
            $table->json('f');
            $table->json('p');
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('updated_at');
        });

        // Compteurs (numérotation des codes participants SS-AAAA-NNNN)
        Schema::create('compteurs', function (Blueprint $table) {
            $table->string('nom', 50)->primary();
            $table->unsignedBigInteger('valeur')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compteurs');
        Schema::dropIfExists('participants');
    }
};
