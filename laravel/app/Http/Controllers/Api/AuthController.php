<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Comptes : création, connexion, déconnexion, mot de passe oublié.
 * L'application reçoit un jeton (Sanctum) qu'elle garde sur l'appareil,
 * ce qui permet aussi de continuer à travailler hors connexion.
 */
class AuthController extends Controller
{
    private function session(User $u): array
    {
        return [
            'token' => $u->createToken('app')->plainTextToken,
            'user' => ['id' => $u->id, 'email' => $u->email],
        ];
    }

    public function register(Request $r): JsonResponse
    {
        $d = $r->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:6', 'max:200'],
            'nom' => ['nullable', 'string', 'max:190'],
            'niveau' => ['nullable', 'string'],
            'province_id' => ['nullable'],
            'region_id' => ['nullable'],
        ]);
        $email = Str::lower(trim($d['email']));
        if (User::where('email', $email)->exists()) {
            return response()->json(['message' => 'User already registered', 'code' => 'user_already_exists'], 422);
        }

        $niveau = in_array($d['niveau'] ?? null, User::NIVEAUX, true) ? $d['niveau'] : 'evaluateur';
        $pv = filled($d['province_id'] ?? null) ? (int) $d['province_id'] : null;
        $rg = filled($d['region_id'] ?? null) ? (int) $d['region_id'] : null;
        if ($pv !== null && ! Province::whereKey($pv)->exists()) {
            $pv = null;
        }
        if ($rg !== null && ! Region::whereKey($rg)->exists()) {
            $rg = null;
        }
        if ($pv !== null && $rg === null) {
            $rg = Province::find($pv)->region_id;
        }

        $u = DB::transaction(function () use ($email, $d, $niveau, $pv, $rg) {
            // Le tout premier compte devient administrateur, actif d'office.
            $premier = ! User::where('role', 'admin')->lockForUpdate()->exists();

            return User::create([
                'email' => $email,
                'password' => $d['password'],
                'nom' => filled($d['nom'] ?? null) ? trim($d['nom']) : null,
                'role' => $premier ? 'admin' : 'evaluateur',
                'actif' => $premier,
                'niveau' => $niveau,
                'province_id' => $pv,
                'region_id' => $rg,
            ]);
        });

        return response()->json($this->session($u), 201);
    }

    public function login(Request $r): JsonResponse
    {
        $d = $r->validate(['email' => ['required', 'string'], 'password' => ['required', 'string']]);
        $u = User::where('email', Str::lower(trim($d['email'])))->first();
        if (! $u || ! self::verifier($d['password'], $u->password)) {
            return response()->json(['message' => 'Invalid login credentials', 'code' => 'invalid_credentials'], 400);
        }
        if (! str_starts_with($u->password, '$2y$') || Hash::needsRehash($u->password)) {
            $u->forceFill(['password' => $d['password']])->save(); // remis au format Laravel
        }

        return response()->json($this->session($u));
    }

    /**
     * Vérifie un mot de passe. Accepte aussi les anciens mots de passe importés
     * de Supabase (bcrypt « $2a$ »), qui sont ensuite réenregistrés au format Laravel.
     */
    private static function verifier(string $clair, string $hash): bool
    {
        if (str_starts_with($hash, '$2a$') || str_starts_with($hash, '$2b$')) {
            return password_verify($clair, $hash);
        }

        return Hash::check($clair, $hash);
    }

    public function logout(Request $r): JsonResponse
    {
        $r->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function me(Request $r): JsonResponse
    {
        return response()->json($r->user()->toProfil());
    }

    /** Envoie le lien de réinitialisation. Réponse identique que le compte existe ou non. */
    public function forgot(Request $r): JsonResponse
    {
        $r->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => Str::lower(trim($r->input('email')))]);

        return response()->json(['ok' => true]);
    }

    /** Nouveau mot de passe depuis le lien reçu par email ; connecte directement l'utilisateur. */
    public function reset(Request $r): JsonResponse
    {
        $r->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'max:200'],
        ]);
        $user = null;
        $status = Password::reset(
            ['email' => Str::lower(trim($r->input('email'))), 'password' => $r->input('password'), 'token' => $r->input('token')],
            function (User $u, string $password) use (&$user) {
                $u->forceFill(['password' => $password])->setRememberToken(Str::random(60));
                $u->save();
                $u->tokens()->delete();
                event(new PasswordReset($u));
                $user = $u;
            }
        );
        if ($status !== Password::PASSWORD_RESET || ! $user) {
            return response()->json(['message' => 'Reset link is invalid or has expired', 'code' => 'otp_expired'], 400);
        }

        return response()->json($this->session($user));
    }

    /** Changer son mot de passe une fois connecté. */
    public function password(Request $r): JsonResponse
    {
        $r->validate(['password' => ['required', 'string', 'min:6', 'max:200']]);
        $u = $r->user();
        if (Hash::check($r->input('password'), $u->password)) {
            return response()->json(['message' => 'New password should be different from the old password.', 'code' => 'same_password'], 422);
        }
        $u->forceFill(['password' => $r->input('password')])->save();
        $u->tokens()->where('id', '!=', $u->currentAccessToken()->id)->delete();

        return response()->json(['ok' => true]);
    }
}
