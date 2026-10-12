<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Limites anti-abus, comptées par adresse email (et non par adresse IP seule :
        // dans un bureau, tous les agents sortent souvent sur internet par la même adresse).
        $email = fn (Request $r) => Str::lower(trim((string) $r->input('email')));
        RateLimiter::for('connexion', fn (Request $r) => [
            Limit::perMinute(10)->by('connexion|'.$email($r).'|'.$r->ip()),
            Limit::perMinute(300)->by('connexion-ip|'.$r->ip()),
        ]);
        RateLimiter::for('inscription', fn (Request $r) => Limit::perMinute(60)->by('inscription|'.$r->ip()));
        RateLimiter::for('mot-de-passe', fn (Request $r) => [
            Limit::perMinute(3)->by('mdp|'.$email($r)),
            Limit::perHour(10)->by('mdp-h|'.$email($r)),
        ]);
        RateLimiter::for('reinitialisation', fn (Request $r) => Limit::perMinute(10)->by('reinit|'.$email($r).'|'.$r->ip()));

        // Lien de réinitialisation : ouvre l'application, qui affiche l'écran « Nouveau mot de passe ».
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return rtrim(config('app.url'), '/').'/?reset='.$token.'&email='.urlencode($user->email);
        });

        ResetPassword::toMailUsing(function ($user, string $token) {
            $url = rtrim(config('app.url'), '/').'/?reset='.$token.'&email='.urlencode($user->email);
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Réinitialisation de votre mot de passe')
                ->greeting('Bonjour '.($user->nom ?: '').',')
                ->line('Vous avez demandé à changer le mot de passe de votre compte Carnet Sport Santé / Sport pour tous.')
                ->action('Choisir un nouveau mot de passe', $url)
                ->line("Ce lien est valable {$minutes} minutes et ne sert qu'une fois.")
                ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.")
                ->salutation('Ministère des Sports, de la Jeunesse et de l\'Emploi');
        });
    }
}
