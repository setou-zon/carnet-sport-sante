<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Découpage territorial officiel : 17 régions, 47 provinces.
 * Les identifiants sont fixes (repris de la base d'origine) : ne pas les renuméroter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('nom')->unique();
            $table->string('chef_lieu')->nullable();
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->unsignedSmallInteger('region_id');
            $table->string('nom')->unique();
            $table->string('chef_lieu')->nullable();
            $table->foreign('region_id')->references('id')->on('regions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('regions');
    }
};
