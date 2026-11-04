<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="assets/logo/logo-inverse.svg">
    <img src="assets/logo/logo.svg" alt="GrimpePan" width="330">
  </picture>
</p>

# GrimpePan

Création et affichage de blocs et de voies sur une structure artificielle d'escalade
de type « pan ».

On photographie une zone du mur, on marque les prises de la voie sur la photo, on
donne une cotation, et les autres grimpeurs peuvent la consulter puis la cocher
comme réussie.

**Pile technique** : Symfony 8.1 · PHP ≥ 8.4.1 · Twig · Doctrine ORM · MariaDB 11.8 ·
JavaScript natif (aucune étape de build) · AssetMapper.

---

## Sommaire

- [Fonctionnalités](#fonctionnalités)
- [Règles métier](#règles-métier)
- [Installation avec l'assistant web](#installation-avec-lassistant-web)
- [Installation manuelle sur un serveur Apache](#installation-manuelle-sur-un-serveur-apache)
- [Mise à jour du serveur](#mise-à-jour-du-serveur)
- [Publier une nouvelle version](#publier-une-nouvelle-version)
- [Installation en développement (macOS)](#installation-en-développement-macos)
- [Base de données MariaDB locale](#base-de-données-mariadb-locale)
- [Commandes utiles](#commandes-utiles)
- [Structure du projet](#structure-du-projet)
- [Choix techniques](#choix-techniques)
- [Identité visuelle](#identité-visuelle)
- [Prochaines étapes](#prochaines-étapes)
- [Licence](#licence)

---

## Fonctionnalités

- **Comptes** : inscription (email, pseudo, mot de passe), connexion avec « se souvenir de moi », déconnexion.
- **Secteurs** : zones du mur (pan, dévers, dalle…), chacune avec ses photos et ses voies.
- **Création de voie** :
  - envoi d'une photo (JPEG, PNG ou WebP) ou réutilisation d'une photo du secteur.
    La photo est redressée selon l'EXIF et réduite à **2000 × 2000 px maximum**,
    proportions conservées, pour économiser la place disque ;
  - marquage des prises sur la photo : départ, main, pied, top ;
  - cotation française (3a → 9c) ou couleur (vert → blanc).
- **Éditeur de prises** (tactile et souris) :
  - toucher la photo pose une prise ;
  - toucher une prise change son type, la faire glisser la déplace ;
  - la gomme efface une prise, « Annuler » revient en arrière ;
  - le curseur « Taille » règle les prochaines prises **et la dernière posée ou déplacée**.
- **Affichage d'une voie** : photo assombrie, prises entourées de la couleur de leur type.
- **Liste des voies** filtrable par secteur, échelle et plage de difficulté.
- **Croix** : « J'ai réussi cette voie », que l'on peut aussi retirer.
- **Modification** d'une voie par son créateur, selon les règles ci-dessous.
- **Suppression** par le créateur ou un admin, avec un garde-fou pour les voies déjà réussies.
- **Mises à jour** : alerte aux admins quand une nouvelle version est publiée, et mise à jour en un clic.

## Règles métier

| Situation | Créateur | Autre grimpeur | Admin |
|---|---|---|---|
| Voie que **personne d'autre** n'a réussie | modifie **tout** : photo, prises, cotation, nom, description | — | — |
| Voie **réussie** par au moins un autre grimpeur | modifie **seulement le nom et la description** | — | — |
| Supprimer une voie non réussie | simple confirmation | — | simple confirmation |
| Supprimer une voie réussie | doit taper **`SUPPRIMER`** | — | doit taper **`SUPPRIMER`** |
| Poser / retirer sa croix | oui | oui | oui |

Précisions :

- La croix du **créateur** sur sa propre voie ne la verrouille pas : seule celle d'un autre grimpeur le fait.
- Si toutes les croix des autres grimpeurs sont retirées, la voie redevient entièrement modifiable.
- Supprimer une voie supprime aussi ses prises et ses croix.
- Les règles de modification sont dans `src/Security/VoieVoter.php`. Le serveur vérifie toutes ces règles, pas seulement l'interface.

## Installation avec l'assistant web

C'est la façon la plus simple d'installer GrimpePan, et elle convient aussi aux
hébergements mutualisés sans accès SSH. L'assistant se trouve dans
`public/setup/` et s'ouvre dans un navigateur.

### Récupérer l'archive

Télécharge **`grimpepan-vX.Y.Z.zip`** depuis la
[page des releases](https://github.com/johnburnout/GrimpePan/releases/latest).
Elle contient tout ce qu'il faut, y compris `vendor/` : l'assistant ne lance pas
Composer. L'empreinte de l'archive est fournie à côté (`.sha256`).

Envoie le contenu du dossier `grimpepan/` sur le serveur, puis fais pointer la
racine web sur le dossier **`public/`**.

> **Fichiers cachés** : `.env`, `public/.htaccess` et `public/uploads/.htaccess`
> commencent par un point, et beaucoup de clients FTP les ignorent par défaut
> (FileZilla : *Serveur → Forcer l'affichage des fichiers cachés*). L'assistant
> les recrée s'ils manquent ; vérifie quand même qu'ils sont bien sur le serveur. Les prérequis sont les mêmes que pour
l'installation manuelle : PHP 8.4.1 ou plus, MariaDB 11.8, Apache avec
`mod_rewrite`, et l'extension PHP `zip` pour les mises à jour en un clic.

Pour construire l'archive toi-même à partir du code : `bin/construire-release.sh`
(voir [Publier une nouvelle version](#publier-une-nouvelle-version)).

### Lancer l'assistant

Ouvre `https://ton-domaine/setup/`. Tant que GrimpePan n'est pas installé, la
page d'accueil du site y redirige d'elle-même.

| Étape | Ce que fait l'assistant |
|---|---|
| 1. Prérequis | Vérifie PHP, les extensions, les droits d'écriture et les limites d'envoi. Les points rouges bloquent, les orange sont des conseils. |
| 2. Base de données | Teste la connexion MariaDB, crée la base si besoin, puis écrit `.env.local` (`APP_ENV=prod`, `APP_SECRET` aléatoire, `DATABASE_URL`). Si PHP n'a pas le droit de créer ce fichier, il affiche son contenu pour que tu le crées toi-même. |
| 3. Tables | Exécute les migrations Doctrine. |
| 4. Administrateur | Crée le premier compte, avec le rôle administrateur. |
| 5. Finalisation | Compile les logos, prépare le cache, **se bloque puis supprime le dossier `public/setup/`**. |

### Sécurité de l'assistant

- **Lance l'installation juste après l'envoi des fichiers.** Avant l'étape 2,
  n'importe quel visiteur pourrait ouvrir l'assistant.
- Dès l'étape 2, l'installation est **réservée au navigateur qui l'a commencée**
  (fichier `var/setup-en-cours.lock`). Un autre navigateur reçoit une erreur 403.
- Une fois l'installation terminée, `var/installation.lock` bloque l'assistant
  **définitivement**, même si le dossier `public/setup/` revient (après un
  `git pull`, par exemple).
- Sur un serveur installé à la main (avec un `.env.local` déjà présent),
  l'assistant refuse aussi de fonctionner.
- Si le serveur web n'a pas le droit de supprimer `public/setup/`, l'assistant
  le signale : **supprime le dossier toi-même**.

**Pour recommencer une installation** (en cas d'erreur ou de navigateur fermé),
supprime sur le serveur `.env.local`, `var/setup-en-cours.lock` et
`var/installation.lock`, remets le dossier `public/setup/` si besoin, puis
rouvre `/setup/`.

## Installation manuelle sur un serveur Apache

Pour un serveur où tu as un accès SSH et la main sur Apache (VPS, serveur dédié).
Cette méthode n'utilise pas l'assistant : supprime le dossier `public/setup/` après
l'installation.

Les commandes ci-dessous visent **Debian 13 (Trixie)**, qui fournit directement
PHP 8.4 et MariaDB 11.8. Sur Ubuntu ou une Debian plus ancienne, il faut ajouter
le dépôt PHP de Ondřej Surý (PHP 8.4) et le dépôt officiel de MariaDB (11.8) ;
le reste est identique.

Conventions utilisées (à adapter) :

| | Valeur |
|---|---|
| Domaine | `grimpepan.example.org` |
| Dossier de l'application | `/var/www/grimpepan` |
| Utilisateur qui déploie | `deploy` |
| Utilisateur d'Apache et de PHP-FPM | `www-data` |

> **Hébergement mutualisé** (pas d'accès à la configuration d'Apache) : voir
> [la variante en fin de section](#variante--hébergement-mutualisé).

### 1. Paquets

```bash
sudo apt update
sudo apt install apache2 mariadb-server git unzip composer \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-gd php8.4-intl \
  php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip
```

L'extension `exif`, nécessaire au redressement des photos, est incluse dans
`php8.4-common` sous Debian. Pour vérifier :

```bash
php -m | grep -E "pdo_mysql|gd|exif|intl"
```

### 2. Apache et PHP-FPM

Apache transmet les fichiers `.php` à PHP-FPM, avec le MPM *event*. Il n'y a pas
de `mod_php`.

```bash
sudo a2dismod php8.4 mpm_prefork 2>/dev/null   # si mod_php était installé
sudo a2enmod mpm_event proxy_fcgi setenvif rewrite headers
sudo a2enconf php8.4-fpm
sudo systemctl restart php8.4-fpm apache2
```

### 3. Réglages PHP

Le formulaire accepte des photos jusqu'à 15 Mo, mais PHP les limite par défaut à
2 Mo. Crée `/etc/php/8.4/fpm/conf.d/90-grimpepan.ini` :

```ini
upload_max_filesize = 16M
post_max_size = 20M
memory_limit = 256M        ; redressement des grandes photos (GD)

; Performances en production
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0   ; vider le cache au déploiement (voir « Mise à jour »)
realpath_cache_size = 4096K
realpath_cache_ttl = 600
```

```bash
sudo systemctl reload php8.4-fpm
```

### 4. Base de données

```bash
sudo mariadb
```

```sql
CREATE DATABASE grimpepan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'grimpepan'@'localhost' IDENTIFIED BY 'mot-de-passe-solide';
GRANT ALL PRIVILEGES ON grimpepan.* TO 'grimpepan'@'localhost';
FLUSH PRIVILEGES;
```

Si le mot de passe contient des caractères spéciaux (`@`, `:`, `/`, `#`…), il
faut l'encoder dans l'URL de connexion (par exemple `@` devient `%40`).

### 5. Code de l'application

```bash
sudo mkdir -p /var/www/grimpepan
sudo chown deploy:www-data /var/www/grimpepan
git clone https://github.com/johnburnout/GrimpePan.git /var/www/grimpepan
cd /var/www/grimpepan
```

Crée `/var/www/grimpepan/.env.local`. Ce fichier n'est **pas versionné** et
contient les secrets :

```bash
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<résultat de : openssl rand -hex 32>
DATABASE_URL="mysql://grimpepan:mot-de-passe-solide@127.0.0.1:3306/grimpepan?serverVersion=11.8.6-MariaDB&charset=utf8mb4"
```

```bash
chmod 640 .env.local
```

Puis installe et initialise l'application :

```bash
composer install --no-dev --optimize-autoloader
composer dump-env prod                          # compile .env* en .env.local.php (plus rapide)
php bin/console doctrine:migrations:migrate -n
php bin/console asset-map:compile               # logos et favicon (dossier assets/)
php bin/console cache:clear
```

### 6. Droits sur les fichiers

PHP-FPM (`www-data`) doit pouvoir écrire dans `var/` (cache, logs) et dans le
dossier des photos. Il n'a besoin d'écrire nulle part ailleurs.

```bash
cd /var/www/grimpepan
sudo chown -R deploy:www-data .
sudo find . -type d -exec chmod 750 {} +
sudo find . -type f -exec chmod 640 {} +
sudo chmod 750 bin/console
sudo chmod -R g+w var public/uploads/photos
sudo find var public/uploads/photos -type d -exec chmod g+s {} +   # nouveaux fichiers dans le groupe www-data
```

> Le dossier `public/uploads/photos/` contient les photos envoyées. Elles ne
> sont **pas versionnées** : ce dossier doit être **conservé** à chaque
> déploiement et **sauvegardé** avec la base.

### 7. VirtualHost

Crée `/etc/apache2/sites-available/grimpepan.conf` :

```apache
<VirtualHost *:80>
    ServerName grimpepan.example.org
    DocumentRoot /var/www/grimpepan/public

    <Directory /var/www/grimpepan/public>
        DirectoryIndex index.php
        # Configuration complète ici : les .htaccess ne sont pas lus (plus rapide)
        AllowOverride None
        Require all granted
        FallbackResource /index.php
    </Directory>

    # Dossier des photos : uniquement des images, aucun script
    <Directory /var/www/grimpepan/public/uploads>
        Options -Indexes -ExecCGI
        FallbackResource disabled
        <FilesMatch ".">
            Require all denied
        </FilesMatch>
        <FilesMatch "(?i)\.(jpe?g|png|webp)$">
            Require all granted
        </FilesMatch>
    </Directory>

    # PHP-FPM
    <FilesMatch "\.php$">
        SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost"
    </FilesMatch>

    # Cache navigateur longue durée pour les fichiers versionnés par AssetMapper
    <Directory /var/www/grimpepan/public/assets>
        Header set Cache-Control "public, max-age=31536000, immutable"
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/grimpepan_error.log
    CustomLog ${APACHE_LOG_DIR}/grimpepan_access.log combined
</VirtualHost>
```

```bash
sudo a2ensite grimpepan
sudo a2dissite 000-default
sudo apache2ctl configtest
sudo systemctl reload apache2
```

### 8. HTTPS

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d grimpepan.example.org
```

Certbot crée le VirtualHost `*:443`, ajoute la redirection HTTP → HTTPS et
programme le renouvellement automatique du certificat.

### 9. Vérification

- https://grimpepan.example.org affiche la liste des voies ;
- créer un compte, un secteur, puis une voie avec une photo de plusieurs Mo ;
- en cas d'erreur : `/var/log/apache2/grimpepan_error.log` et
  `/var/www/grimpepan/var/log/prod.log`.

### Variante : hébergement mutualisé

Sans accès à la configuration d'Apache, le projet fournit deux fichiers
`.htaccess` qui jouent le même rôle que le VirtualHost :

| Fichier | Rôle |
|---|---|
| `public/.htaccess` | Envoie toutes les requêtes vers `index.php` (paquet `symfony/apache-pack`) |
| `public/uploads/.htaccess` | N'autorise que les images dans le dossier des photos |

Il faut que l'hébergeur ait **`mod_rewrite`** et **`AllowOverride All`**, et que
la **racine web pointe sur le dossier `public/`**. Si ce n'est pas possible, il
ne faut **pas** publier tout le projet : `.env.local`, `var/` et `vendor/` ne
doivent jamais être accessibles depuis le web.

Les commandes de l'étape 5 se lancent alors en SSH, ou en local avant d'envoyer
les fichiers. Les limites d'envoi de l'étape 3 se règlent dans l'interface de
l'hébergeur ou dans un `.user.ini`.

## Mise à jour du serveur

GrimpePan vérifie **une fois par jour** si une nouvelle release a été publiée
sur GitHub. Si c'est le cas, les administrateurs voient un bandeau en haut des
pages, et le détail se trouve sur la page **Mises à jour** (`/admin/mise-a-jour`).

> **Avant toute mise à jour, sauvegarde la base** (outil de l'hébergeur, ou
> `mariadb-dump -u grimpepan -p grimpepan > grimpepan-$(date +%F).sql`).
> Le code, lui, est sauvegardé automatiquement.

### En un clic, depuis l'administration

Sur la page **Mises à jour**, le bouton « Mettre à jour » :

1. télécharge l'archive de la release et vérifie son **empreinte SHA-256** ;
2. contrôle son contenu, puis passe le site **en maintenance** (page 503 pour les visiteurs) ;
3. remplace le code. L'ancien est déplacé dans `var/mise-a-jour/precedente-X.Y.Z-…/` ;
4. avec le nouveau code : vide le cache, lance les **migrations** de la base,
   recompile les logos, puis **retire la maintenance**.

Ne sont **jamais touchés** : `.env.local`, `var/` et les photos
(`public/uploads/photos/`). L'assistant `public/setup/` n'est pas réinstallé.

Cette méthode suppose que le serveur web (PHP) puisse écrire dans la racine du
projet, dans `public/` et dans `var/` : c'est généralement le cas en
hébergement mutualisé. Sinon, la page le signale et propose la commande SSH.

### En SSH

```bash
cd /var/www/grimpepan
php bin/console app:mise-a-jour --verifier   # y a-t-il une nouvelle version ?
php bin/console app:mise-a-jour              # l'installer (même mécanisme qu'en un clic)
sudo systemctl reload php8.4-fpm             # vide l'OPcache (validate_timestamps = 0)
```

Lance la commande avec l'utilisateur propriétaire des fichiers (`deploy` dans
l'installation manuelle).

### Revenir à la version précédente

L'ancienne version reste dans `var/mise-a-jour/precedente-X.Y.Z-…/`, qui
contient tous les fichiers remplacés. Pour revenir en arrière, remets ces
éléments à la racine, restaure la sauvegarde de la base si une migration a été
appliquée, puis vide le cache (`rm -rf var/cache/prod`).

### Si une mise à jour est interrompue

Le mode maintenance (`var/maintenance.lock`) se lève tout seul après
**30 minutes**. Pour le lever tout de suite, supprime ce fichier.

### Installation faite avec git (sans archive)

```bash
cd /var/www/grimpepan
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate -n
php bin/console asset-map:compile
php bin/console cache:clear
sudo systemctl reload php8.4-fpm
```

### Notes propres à certaines versions

- **Réduction des photos** : après la mise à jour qui l'introduit, lance une fois
  `php bin/console app:photos:reduire` pour réduire les photos déjà envoyées.
  Commence par `--simulation` pour voir ce qui sera fait.

## Publier une nouvelle version

Les versions suivent [SemVer](https://semver.org/lang/fr/) : `MAJEURE.MINEURE.CORRECTIF`.

1. Mets à jour le fichier **`VERSION`** (par exemple `1.1.0`).
2. Ajoute une section **`## [1.1.0] - AAAA-MM-JJ`** en tête de `CHANGELOG.md` :
   elle devient les notes de la release, que les administrateurs lisent avant
   de mettre à jour.
3. Commite, crée le tag et pousse :

   ```bash
   git commit -am "Version 1.1.0"
   git tag -a v1.1.0 -m "GrimpePan 1.1.0"
   git push && git push origin v1.1.0
   ```

Le workflow GitHub Actions `.github/workflows/release.yml` fait le reste :

- il vérifie que le tag correspond au fichier `VERSION` ;
- il construit l'archive `grimpepan-v1.1.0.zip` (code, `vendor/` sans les
  paquets de dev, paquets JavaScript) et son empreinte `.sha256`, avec
  `bin/construire-release.sh` ;
- il publie la release.

Les sites installés la proposent ensuite à leurs administrateurs, dans un délai
de 24 h au plus.

Pour construire l'archive en local (pour la tester, par exemple) :
`bin/construire-release.sh build/`.

> **Contrat entre versions** : la route `/admin/mise-a-jour/finaliser` et son
> paramètre `jeton` doivent exister dans toutes les versions. C'est la
> **nouvelle** version qui reçoit cette requête, juste après le remplacement du code.

## Installation en développement (macOS)

En développement, l'application tourne avec le serveur local de la Symfony CLI,
sans Apache. Les fichiers `.htaccess` ne servent qu'en production.

### Prérequis

- PHP ≥ 8.4 avec les extensions `pdo_mysql`, `gd`, `exif` et `intl`
- Composer 2
- Symfony CLI (serveur local)
- MariaDB 11.8 (voir [plus bas](#base-de-données-mariadb-locale))

```bash
brew install php composer
brew install symfony-cli/tap/symfony-cli
symfony check:requirements
```

### Mise en route

```bash
git clone https://github.com/johnburnout/GrimpePan.git
cd GrimpePan
composer install

# Base de données (MariaDB doit tourner sur le port 3307, voir plus bas)
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n

# Serveur local en arrière-plan
symfony serve -d
symfony open:local
```

Puis créer un compte, un secteur, puis une voie.

En développement, `APP_SECRET` est fourni par `.env.dev`. Pour surcharger une
variable sur ta machine (identifiants de base, par exemple), utilise
`.env.local`, qui n'est pas versionné.

### HTTPS en local (facultatif)

```bash
symfony server:ca:install     # demande le mot de passe macOS
symfony server:stop && symfony serve -d
```

L'application est alors servie sur https://127.0.0.1:8000.

### Taille des photos envoyées

Le formulaire accepte des photos jusqu'à **15 Mo**, mais PHP limite par défaut
les envois à 2 Mo. Le fichier `php.ini` à la racine du projet relève ces limites
(`upload_max_filesize = 16M`, `post_max_size = 20M`, `memory_limit = 256M`). La
Symfony CLI le lit automatiquement : il suffit de redémarrer le serveur après
l'avoir modifié (`symfony server:stop && symfony serve -d`).

Ce fichier ne sert qu'en développement. Sur le serveur, voir
l'[étape 3](#3-réglages-php) de l'installation Apache.

## Base de données MariaDB locale

La production tourne sous **MariaDB 11.8.6**. Le développement utilise donc
MariaDB 11.8, et non MySQL : les migrations Doctrine dépendent de la plateforme,
et Doctrine ne sait pas générer une migration MariaDB à partir d'un serveur MySQL.

Sur la machine de développement, MariaDB tourne **à côté** d'un MySQL existant
(port 3306) :

| | Valeur |
|---|---|
| Paquet | `mariadb@11.8` (Homebrew, keg-only) |
| Adresse | `127.0.0.1:3307`, utilisateur `root` sans mot de passe |
| Données | `/opt/homebrew/var/mariadb-11.8` |
| Démarrage | LaunchAgent `~/Library/LaunchAgents/local.mariadb-11.8.plist` |
| Base | `grimpepan` |

> ⚠️ **Ne pas utiliser `brew services start mariadb@11.8`** : ce service
> démarrerait MariaDB sur `/opt/homebrew/var/mysql`, le dossier de données de
> MySQL, et risquerait de l'abîmer.

Installation de zéro :

```bash
brew install mariadb@11.8
M=/opt/homebrew/opt/mariadb@11.8
mkdir -p /opt/homebrew/var/mariadb-11.8
$M/bin/mariadb-install-db --no-defaults --basedir=$M \
  --datadir=/opt/homebrew/var/mariadb-11.8 \
  --auth-root-authentication-method=normal --skip-test-db
```

Le LaunchAgent lance `mariadbd --no-defaults --port=3307 --bind-address=127.0.0.1
--socket=/tmp/mariadb-3307.sock --datadir=/opt/homebrew/var/mariadb-11.8
--character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci`.

```bash
# démarrer / arrêter
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/local.mariadb-11.8.plist
launchctl bootout  gui/$(id -u)/local.mariadb-11.8

# console
/opt/homebrew/opt/mariadb@11.8/bin/mariadb -h127.0.0.1 -P3307 -uroot grimpepan
```

La connexion par défaut se trouve dans `.env` :

```
DATABASE_URL="mysql://root@127.0.0.1:3307/grimpepan?serverVersion=11.8.6-MariaDB&charset=utf8mb4"
```

**Avec Docker**, `compose.yaml` fournit aussi un conteneur `mariadb:11.8.6`.
Avec `docker compose up -d`, la Symfony CLI détecte le conteneur et fournit
elle-même le bon `DATABASE_URL`.

## Commandes utiles

```bash
# Serveur
symfony serve -d               # démarrer en arrière-plan
symfony server:log             # suivre les logs
symfony server:stop            # arrêter

# Base de données
php bin/console make:migration                 # après une modification d'entité
php bin/console doctrine:migrations:migrate -n # appliquer les migrations
php bin/console doctrine:schema:validate       # vérifier mapping et schéma

# Photos
php bin/console app:photos:reduire --simulation  # liste les photos de plus de 2000 px
php bin/console app:photos:reduire               # les réduit (fichiers et dimensions en base)

# Vérifications
php bin/console lint:twig templates
php bin/console lint:container
php bin/console debug:router
```

**Créer un compte administrateur** (le mot de passe est demandé de façon masquée) :

```bash
php bin/console app:utilisateur:creer admin@exemple.org Admin --admin
```

Pour donner le rôle à un compte existant (pas encore d'interface) :

```sql
UPDATE utilisateur SET roles = '["ROLE_ADMIN"]' WHERE email = 'adresse@exemple.org';
```

L'utilisateur doit se déconnecter puis se reconnecter pour que le rôle soit pris en compte.

## Structure du projet

| Emplacement | Contenu |
|---|---|
| `src/Entity` | `User`, `Secteur`, `Photo`, `Voie`, `Prise`, `Croix` |
| `src/Enum` | `CotationFr`, `CotationCouleur`, `SystemeCotation`, `TypePrise` |
| `src/Controller` | Accueil, inscription, connexion, secteurs, voies (création, modification, croix, suppression) |
| `src/Form` | `RegistrationFormType`, `SecteurType`, `VoieType` (option `trace` : formulaire complet ou nom + description) |
| `src/Security/VoieVoter.php` | Droits `VOIE_MODIFIER` et `VOIE_MODIFIER_TRACE` |
| `src/Service/PhotoUploader.php` | Enregistrement des photos envoyées |
| `src/Service/PhotoOptimiseur.php` | Redressement EXIF et réduction à 2000 × 2000 px |
| `src/Command/ReduirePhotosCommand.php` | Commande `app:photos:reduire` (photos déjà enregistrées) |
| `templates/voie/_form.html.twig` | Formulaire complet, commun à la création et à la modification |
| `templates/voie/_photo.html.twig` | Rendu photo + prises (SVG) |
| `public/js/editeur.js` | Éditeur de prises |
| `public/css/app.css` | Styles (palette alignée sur le logo) |
| `public/fonts/bricolage-grotesque/` | Police des titres et du logo, avec sa licence |
| `assets/logo/` | Logos SVG et favicon |
| `public/setup/` | Assistant d'installation web (supprimé après l'installation) |
| `src/Command/CreerUtilisateurCommand.php` | Commande `app:utilisateur:creer` (compte administrateur…) |
| `src/MiseAJour/` | Vérification des releases GitHub, installation d'une mise à jour, mode maintenance |
| `src/Controller/AdminMiseAJourController.php` | Page « Mises à jour » (`/admin/mise-a-jour`) |
| `src/Command/MiseAJourCommand.php` | Commande `app:mise-a-jour` |
| `VERSION`, `CHANGELOG.md` | Version installée et journal des versions |
| `bin/construire-release.sh` | Construction de l'archive de release |
| `.github/workflows/release.yml` | Publication automatique d'une release à chaque tag `vX.Y.Z` |
| `migrations/` | Migrations Doctrine (générées sur MariaDB) |
| `public/.htaccess` | Réécriture d'URL pour Apache sans VirtualHost (`symfony/apache-pack`) |
| `public/uploads/.htaccess` | N'autorise que les images dans le dossier des photos |
| `php.ini` | Limites PHP du serveur de développement (photos jusqu'à 16 Mo) |
| `compose.yaml` | Conteneurs Docker facultatifs (MariaDB, Mailpit) |

## Choix techniques

- **Photos réduites à 2000 × 2000 px** (JPEG qualité 85, progressif ; PNG et WebP
  conservés dans leur format). Une photo déjà assez petite n'est pas réenregistrée,
  donc pas recompressée. Les prises étant en pourcentage, une réduction ne les
  décale pas.
- **Prises en pourcentage** : x, y et rayon sont stockés en % de l'image, pour
  rester alignés quelle que soit la taille d'affichage.
- **Cotation** stockée en texte (`6a`, `bleu`) avec un **rang** numérique pour
  trier et filtrer par difficulté, quelle que soit l'échelle.
- **Pas de build JavaScript** : l'éditeur est en JavaScript natif, servi depuis `public/js/`.
- **CSRF** : protection « stateless » de Symfony 8, qui vérifie l'en-tête
  `Origin` envoyé par le navigateur.
- **Voie validée** : `Voie::estValidee()` est vraie dès qu'une croix vient d'un
  autre grimpeur que l'auteur.

## Identité visuelle

| Fichier (`assets/logo/`) | Usage |
|---|---|
| `logo-inverse.svg` | En-tête (fond sombre) |
| `mark-inverse.svg` | En-tête sur petit écran (≤ 480 px) |
| `favicon.svg` | Icône d'onglet |
| `logo.svg`, `mark.svg`, `mark-mono.svg` | Fonds clairs, impression |

- **Couleurs** : sombre `#1C1F24`, orange `#E8622C`, clair `#F4F2EE`.
- **Police** : *Bricolage Grotesque* (variable, graisses 200 à 800), auto-hébergée,
  sans appel à Google Fonts. Elle est utilisée pour le logo et les titres. Le texte
  courant reste en police système.
- Le logo de l'en-tête est inséré en SVG *inline* (`{{ source('@logo/…') }}`) :
  chargé en `<img>`, il ne pourrait pas utiliser la police de la page.

## Prochaines étapes

- Plusieurs voies affichées sur une même photo
- Page « Mes voies », avis de cotation, commentaires
- Administration : gestion des rôles, modération, archivage des voies démontées
- Tests automatisés (PHPUnit)

## Licence

Code : GNU GPL v3 ou ultérieure (`GPL-3.0-or-later`), voir [`LICENSE`](LICENSE).
Police Bricolage Grotesque : SIL Open Font License 1.1, voir
[`public/fonts/bricolage-grotesque/LICENSE.txt`](public/fonts/bricolage-grotesque/LICENSE.txt).
