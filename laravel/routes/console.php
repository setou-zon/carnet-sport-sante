<?php

use Illuminate\Support\Facades\Schedule;

/*
| Tâches planifiées. Sur le serveur, une seule ligne cron suffit :
|   * * * * * cd /var/www/sportsante && php artisan schedule:run >> /dev/null 2>&1
*/

// Supprime les liens de réinitialisation de mot de passe expirés
Schedule::command('auth:clear-resets')->daily();
