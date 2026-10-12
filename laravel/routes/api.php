<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\ProfilController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\TerritoireController;
use App\Http\Controllers\Api\TransmissionController;
use Illuminate\Support\Facades\Route;

/*
| API de l'application Carnet Sport Santé / Sport pour tous.
| Toutes les routes, sauf la connexion et le découpage territorial,
| demandent un jeton (en-tête Authorization: Bearer ...).
*/

// Public
Route::get('regions', [TerritoireController::class, 'regions']);
Route::get('provinces', [TerritoireController::class, 'provinces']);

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:inscription');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:connexion');
    Route::post('forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:mot-de-passe');
    Route::post('reset-password', [AuthController::class, 'reset'])->middleware('throttle:reinitialisation');
});

// Connecté
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/password', [AuthController::class, 'password']);

    Route::get('profiles', [ProfilController::class, 'index']);
    Route::patch('profiles/{profil}', [ProfilController::class, 'update']);

    // Carnet Sport Santé
    Route::get('participants', [ParticipantController::class, 'index']);
    Route::post('participants', [ParticipantController::class, 'store']);
    Route::post('participants/supprimer', [ParticipantController::class, 'destroyMany']);
    Route::patch('participants/{participant}', [ParticipantController::class, 'update']);
    Route::delete('participants/{participant}', [ParticipantController::class, 'destroy']);

    // Sport pour tous
    Route::get('sites', [SiteController::class, 'index']);
    Route::post('sites', [SiteController::class, 'store']);
    Route::patch('sites/{site}', [SiteController::class, 'update']);
    Route::delete('sites/{site}', [SiteController::class, 'destroy']);

    Route::get('presences', [PresenceController::class, 'index']);
    Route::post('presences', [PresenceController::class, 'store']);
    Route::put('presences', [PresenceController::class, 'upsert']);
    Route::patch('presences/{presence}', [PresenceController::class, 'update']);
    Route::delete('presences/{presence}', [PresenceController::class, 'destroy']);

    Route::get('transmissions', [TransmissionController::class, 'index']);
    Route::put('transmissions', [TransmissionController::class, 'upsert']);
});
