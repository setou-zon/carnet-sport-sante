# Carnet Sport Santé & Sport pour tous — version Laravel

Plateforme du **Ministère des Sports, de la Jeunesse et de l'Emploi (MSJE / DGSL / DST)** :

- **Carnet Sport Santé** : les évaluateurs saisissent les tests de condition physique et remettent un carnet PDF au participant ;
- **Sport pour tous** : les promoteurs saisissent les présences hebdomadaires de leur site, puis les rapports remontent **Site → DPSJE (province) → DRSJE (région) → SSEC/DST (national)**.

Cette version remplace l'ancien serveur Supabase par une application **Laravel 13**, installée sur un serveur du ministère. Les écrans, le carnet PDF, le mode hors connexion et l'installation sur téléphone sont identiques à la version actuelle.

---

## Sommaire

1. [Architecture](#1-architecture)
2. [Prérequis du serveur](#2-prérequis-du-serveur)
3. [Installation](#3-installation)
4. [Transfert des données depuis Supabase](#4-transfert-des-données-depuis-supabase)
5. [Exploitation au quotidien](#5-exploitation-au-quotidien)
6. [Organisation du code](#6-organisation-du-code)
7. [Règles d'accès](#7-règles-daccès)
8. [API](#8-api)
9. [Tests](#9-tests)
10. [Sécurité](#10-sécurité)

---

## 1. Architecture

```
 Téléphone / ordinateur                         Serveur du ministère
 ┌──────────────────────────┐   HTTPS     ┌────────────────────────────────────┐
 │ Application (PWA)        │ ──────────▶ │ Nginx + PHP-FPM                     │
 │  public/app.html         │   /api/...  │  Laravel 13                         │
 │  - écrans, carnet PDF    │ ◀────────── │   routes/api.php   → contrôleurs    │
 │  - travail hors ligne    │    JSON     │   app/Policies     → qui a le droit │
 │  - jeton de connexion    │             │   app/Models/User  → territoires    │
 └──────────────────────────┘             │  MySQL / MariaDB (ou PostgreSQL)    │
                                          │  Serveur SMTP (emails)              │
                                          └────────────────────────────────────┘
```

- **Écrans** : une seule page, `public/app.html` (HTML, CSS et JavaScript, **sans étape de compilation** : pas de Node.js, pas de Vite). Laravel la sert à l'adresse `/`.
- **Hors connexion** : `public/sw.js` (service worker) garde l'application en cache. Les carnets et fiches saisis hors connexion sont gardés sur l'appareil et envoyés automatiquement au retour du réseau. **Les réponses de l'API ne sont jamais mises en cache par le service worker.**
- **Connexion** : Laravel Sanctum, par jeton. Le jeton reste sur l'appareil pour permettre le travail hors connexion. Désactiver un compte coupe immédiatement tous ses accès.
- **Police et générateur PDF** : servis par le serveur lui-même (`public/fonts`, `public/vendor`). L'application ne dépend d'aucun site extérieur.

## 2. Prérequis du serveur

| Élément | Version |
|---|---|
| Système | Linux (Ubuntu 24.04 LTS ou Debian 12 conseillés) |
| PHP | **8.3 ou plus**, avec les extensions `pdo_mysql` (ou `pdo_pgsql`), `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl` |
| Composer | 2.x |
| Base de données | MySQL 8, MariaDB 10.11 ou plus, ou PostgreSQL 14 ou plus |
| Serveur web | Nginx (ou Apache) avec **HTTPS obligatoire** (sans HTTPS, l'installation sur téléphone et le mode hors connexion ne fonctionnent pas) |
| Emails | Un compte SMTP (messagerie du ministère) pour les liens « mot de passe oublié » |

La suite de tests a été passée sur MariaDB 10.11, PostgreSQL 16 et SQLite.

## 3. Installation

Les commandes ci-dessous supposent une installation dans `/var/www/sportsante`.

```bash
# 1. Récupérer le code
git clone https://github.com/setou-zon/carnet-sport-sante.git /opt/carnet-sport-sante
sudo ln -s /opt/carnet-sport-sante/laravel /var/www/sportsante
cd /var/www/sportsante

# 2. Dépendances PHP
composer install --no-dev --optimize-autoloader

# 3. Configuration
cp .env.example .env
nano .env                         # APP_URL, base de données, SMTP (lignes marquées ⚠)
php artisan key:generate

# 4. Base de données
mysql -u root -p -e "CREATE DATABASE sportsante CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'sportsante'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_FORT';
  GRANT ALL ON sportsante.* TO 'sportsante'@'localhost';"
php artisan migrate --force --seed  # crée les tables et charge les 17 régions / 47 provinces

# 5. Droits d'écriture et optimisation
sudo chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache && php artisan route:cache

# 6. Tâches planifiées (crontab de www-data)
#    * * * * * cd /var/www/sportsante && php artisan schedule:run >> /dev/null 2>&1
```

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name sportsante.exemple.gov.bf;
    root /var/www/sportsante/public;

    ssl_certificate     /etc/letsencrypt/live/sportsante.exemple.gov.bf/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/sportsante.exemple.gov.bf/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    client_max_body_size 5m;

    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }

    # Le service worker et la page doivent toujours être rechargés (nouvelles versions)
    location = /sw.js { add_header Cache-Control "no-cache"; try_files $uri =404; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known) { deny all; }
}
server { listen 80; server_name sportsante.exemple.gov.bf; return 301 https://$host$request_uri; }
```

### Premier compte

Ouvrez l'adresse de l'application et touchez **Créer un compte** : **le tout premier compte créé devient administrateur**, actif d'office. Les comptes suivants sont « en attente » jusqu'à ce que l'administrateur les active (menu **Comptes**).

Si vous importez les données de Supabase (section 4), les comptes existants, administrateur compris, sont repris : ne créez pas de compte avant l'import.

## 4. Transfert des données depuis Supabase

Les identifiants, les codes participants (SS-AAAA-NNNN) et **les mots de passe** sont conservés : chacun se connecte avec son email et son mot de passe habituels.

1. **Exporter** : dans Supabase → *SQL Editor*, exécutez `database/supabase/export.sql`, puis copiez le résultat dans un fichier `export.json`.
   > Ce fichier contient des données de santé et les empreintes des mots de passe. Copiez-le directement sur le serveur (clé USB chiffrée ou `scp`), jamais par email ni WhatsApp.
2. **Essayer** (ne modifie rien) :
   ```bash
   php artisan import:supabase /chemin/export.json --essai
   ```
3. **Importer** :
   ```bash
   php artisan import:supabase /chemin/export.json
   rm /chemin/export.json
   ```
   La commande peut être relancée sans créer de doublon : faites un import d'essai pendant la formation, puis l'import final le jour de la bascule.

### Jour de la bascule

1. La veille, demandez à tous les évaluateurs et promoteurs d'**ouvrir l'application connectés à internet**, pour envoyer ce qui est encore enregistré sur leur appareil (pastille « à envoyer » vide).
2. Faites l'export et l'import final.
3. Communiquez la nouvelle adresse. Sur chaque téléphone, ouvrez la nouvelle adresse et réinstallez l'application (bouton **Installer**). L'ancienne icône peut être supprimée.
4. Passez l'ancienne base Supabase en lecture seule, puis arrêtez-la une fois la vérification faite.

## 5. Exploitation au quotidien

### Sauvegardes (indispensable)

```bash
# /etc/cron.d/sportsante-sauvegarde : tous les jours à 2 h, 30 jours conservés
0 2 * * * root mysqldump --single-transaction sportsante | gzip > /var/backups/sportsante-$(date +\%F).sql.gz && find /var/backups -name 'sportsante-*.sql.gz' -mtime +30 -delete
```

Copiez régulièrement ces sauvegardes hors du serveur (autre machine du ministère) et testez une restauration au moins une fois par trimestre :
`gunzip -c sportsante-AAAA-MM-JJ.sql.gz | mysql sportsante_test`.

### Dépannage des comptes

```bash
php artisan compte:admin agent@exemple.bf          # donne le rôle administrateur et active le compte
php artisan compte:mot-de-passe agent@exemple.bf   # mot de passe provisoire, affiché à l'écran
```

### Mise à jour de l'application

```bash
cd /opt/carnet-sport-sante && git pull
cd /var/www/sportsante
php artisan down
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
php artisan up
```

Si vous modifiez `public/app.html` ou les fichiers qu'il utilise, **changez la valeur `VERSION` en haut de `public/sw.js`** (par exemple `sportsante-v2`) : c'est ce qui fait que les téléphones prennent la nouvelle version.

### Journaux

`storage/logs/laravel-AAAA-MM-JJ.log` (un fichier par jour).

## 6. Organisation du code

| Fichier | Rôle |
|---|---|
| `routes/api.php` | Liste de toutes les routes de l'API |
| `app/Http/Controllers/Api/` | Un contrôleur par sujet : `AuthController` (comptes), `ParticipantController` (carnets), `SiteController`, `PresenceController`, `TransmissionController`, `ProfilController`, `TerritoireController` |
| `app/Policies/` | Qui a le droit de créer / modifier / supprimer quoi |
| `app/Models/User.php` | Règles de territoire : `isEval()`, `canSeeProvince()`, `siteRole()`, `canTransmit()`… |
| `app/Models/*.php` | Modèles Eloquent ; `scopeVisiblesPar()` filtre ce que chaque utilisateur peut lire |
| `app/Casts/JsonObject.php` | Garde les champs JSON (carnet, jours) en objets : `{}` reste `{}` |
| `app/Console/Commands/` | `import:supabase`, `compte:admin`, `compte:mot-de-passe` |
| `database/migrations/` | Structure de la base |
| `database/seeders/TerritoiresSeeder.php` | Découpage territorial officiel (17 régions, 47 provinces) |
| `public/app.html` | Les écrans. Le bloc « connexion au serveur (API Laravel) » traduit les demandes des écrans en appels à l'API |
| `public/sw.js` | Service worker (cache de l'application, hors connexion) |
| `public/img/msje.jpg`, `public/img/spt.jpg` | Logos de l'accueil et du carnet PDF : remplaçables par des fichiers du même nom |
| `tests/Feature/` | Tests automatiques des règles |

### Données

| Table | Contenu |
|---|---|
| `users` | Comptes : `role` (evaluateur / admin), `niveau` (evaluateur / promoteur / dpsje / drsje / national), `province_id`, `region_id`, `actif` |
| `regions`, `provinces` | Découpage territorial (identifiants fixes) |
| `participants` | Carnets : `f` (fiche du participant, JSON, avec le code), `p` (résultats T0 / 6 mois / 12 mois, JSON), `owner` (auteur) |
| `compteurs` | Numérotation des codes SS-AAAA-NNNN |
| `sites` | Sites Sport pour tous (F-SITE) |
| `presences` | Fiches hebdomadaires : `jours` `{lun:{f,h}, …}`, totaux recalculés par le serveur, `statut` (soumis / valide / a_corriger) |
| `transmissions` | Rapports transmis : R-MENS (`AAAA-MM`), R-TRIM (`AAAA-Tn`), R-SEM (`AAAA-Sn`) |

## 7. Règles d'accès

| | Carnets santé | Sites | Fiches de présence | Transmissions |
|---|---|---|---|---|
| **Évaluateur** | voit et modifie tous les carnets ; supprime les siens | — | — | — |
| **Promoteur** | **jamais** | son site | saisit et corrige ses fiches ; plus de modification une fois validée | R-MENS de son site |
| **DPSJE** | **jamais** | sites de sa province (crée, modifie, supprime) | contrôle : valide ou renvoie pour correction | R-TRIM de sa province |
| **DRSJE** | **jamais** | lecture, sa région | lecture, sa région | R-SEM de sa région |
| **National (SSEC/DST)** | **jamais** | lecture, tout le pays | lecture, tout le pays | lecture de tout |
| **Administrateur** | tout, y compris suppression | tout | tout | tout |

Règles automatiques côté serveur :
- le code participant **SS-AAAA-NNNN** est attribué par le serveur et ne change plus ;
- l'auteur d'un carnet est toujours la personne connectée ;
- les **totaux** des fiches sont recalculés par le serveur ;
- une fiche saisie ou corrigée par le promoteur repart en statut **soumis** ; quand la DPSJE change le statut, son nom et la date sont enregistrés ;
- un compte **inactif** ne voit rien et ne peut rien saisir ;
- l'administrateur ne peut pas retirer son propre rôle ni se désactiver lui-même.

## 8. API

Toutes les routes sont sous `/api`. Elles renvoient du JSON. Sauf mention contraire, elles demandent l'en-tête `Authorization: Bearer <jeton>`.

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/auth/register` | Créer un compte (public) |
| POST | `/auth/login` | Se connecter → `{token, user}` (public) |
| POST | `/auth/forgot-password` | Envoyer le lien de réinitialisation (public) |
| POST | `/auth/reset-password` | Nouveau mot de passe depuis le lien → `{token, user}` (public) |
| POST | `/auth/logout` | Se déconnecter (révoque le jeton) |
| GET | `/auth/me` | Son profil |
| POST | `/auth/password` | Changer son mot de passe |
| GET | `/regions`, `/provinces` | Découpage territorial (public) |
| GET / PATCH | `/profiles`, `/profiles/{id}` | Comptes (modification : administrateur) |
| GET / POST | `/participants` | Carnets |
| PATCH / DELETE | `/participants/{id}` | Modifier / supprimer un carnet |
| POST | `/participants/supprimer` | Suppression groupée `{ids:[…]}` |
| GET / POST | `/sites` ; PATCH / DELETE `/sites/{id}` | Sites |
| GET `?annee_min=&annee_max=` / POST | `/presences` | Fiches |
| PUT | `/presences` | Enregistrer la fiche d'un site pour une semaine (crée ou met à jour) |
| PATCH / DELETE | `/presences/{id}` | Modifier, contrôler, supprimer |
| GET `?annee=` / PUT | `/transmissions` | Lire / transmettre (retransmettre remplace) |

Codes d'erreur renvoyés dans `code` : `23505` (doublon), `42501` (accès refusé), `P0001` (règle métier, par exemple une fiche déjà validée).

Limites anti-abus : 10 tentatives de connexion par minute et par email ; 3 demandes de lien de mot de passe par minute et 10 par heure, par email. Les limites sont comptées par email : plusieurs agents qui partagent la même connexion internet ne se bloquent pas entre eux.

## 9. Tests

```bash
composer install          # avec les outils de test
php artisan config:clear  # indispensable si la configuration a été mise en cache
vendor/bin/phpunit        # 27 tests : comptes, carnets, territoire, circuit Sport pour tous, import
```

Les tests utilisent une base SQLite en mémoire. Pour les lancer sur MySQL :
`DB_CONNECTION=mysql DB_DATABASE=sportsante_test DB_USERNAME=… DB_PASSWORD=… vendor/bin/phpunit` (sur une base de test vide, jamais la base réelle).

## 10. Sécurité

- **HTTPS obligatoire.** Gardez `APP_DEBUG=false` en production.
- Les mots de passe sont stockés chiffrés (bcrypt). Personne, administrateur compris, ne peut les lire.
- Les données de santé ne quittent pas le serveur : pas de service extérieur, et l'application ne met pas les réponses de l'API en cache.
- N'exportez pas les carnets en CSV pour les envoyer par WhatsApp ou par email.
- Désactivez sans attendre le compte d'un agent qui quitte ses fonctions (menu **Comptes**).
- Sauvegardes quotidiennes et copie hors du serveur (section 5).
