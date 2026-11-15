<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="assets/logo/logo-inverse.svg">
    <img src="assets/logo/logo.svg" alt="GrimPanBloc" width="330">
  </picture>
</p>

<p align="center">
  <a href="README.md">Français</a> · <strong>English</strong> · <a href="README.es.md">Español</a> · <a href="README.it.md">Italiano</a> · <a href="README.ru.md">Русский</a>
</p>

# GrimPanBloc

Creating and displaying boulder problems and routes on an artificial climbing
wall of the “board” type.

You photograph an area of the wall, mark the holds of the route on the photo,
give it a grade, and the other climbers can look it up and then tick it as sent.

**Tech stack**: Symfony 8.1 · PHP ≥ 8.4.1 · Twig · Doctrine ORM · MariaDB 11.8 ·
plain JavaScript (no build step) · AssetMapper.

> The French [README](README.md) is the reference version; this translation may
> lag slightly behind it.

---

## Contents

- [Features](#features)
- [Business rules](#business-rules)
- [Installing with the web wizard](#installing-with-the-web-wizard)
- [Manual installation on an Apache server](#manual-installation-on-an-apache-server)
- [Updating the server](#updating-the-server)
- [Publishing a new version](#publishing-a-new-version)
- [Development setup (macOS)](#development-setup-macos)
- [Local MariaDB database](#local-mariadb-database)
- [Useful commands](#useful-commands)
- [Project structure](#project-structure)
- [Technical choices](#technical-choices)
- [Visual identity](#visual-identity)
- [Next steps](#next-steps)
- [Licence](#licence)

---

## Features

- **Languages**: French, English, Spanish, Italian and Russian. The browser's language
  is used by default; the menu in the footer lets you change it (the choice is remembered,
  and saved in the account of a logged-in climber). The texts are in `translations/`
  (ICU format for plurals); the installation wizard and the commands stay in French.
- **Accounts**: sign-up (email, username, password), login with “remember me”, logout.
- **Sectors**: areas of the wall (board, overhang, slab…), each with its photos and routes.
- **Creating a route**:
  - upload a photo (JPEG, PNG or WebP) or reuse a photo of the sector.
    The photo is rotated according to its EXIF data and reduced to **2000 × 2000 px maximum**,
    keeping its proportions, to save disk space. A **thumbnail**
    (800 px per side at most) is used in route lists and in the administration;
  - marking the holds on the photo: start, hand, foot, top;
  - French grade (3a → 9c) or colour (green → white).
- **Hold editor** (touch and mouse):
  - tapping the photo places a hold;
  - tapping a hold changes its type, dragging it moves it;
  - the eraser deletes a hold, “Undo” goes back;
  - the “Size” slider sets the next holds **and the last one placed or moved**.
- **Route display**: darkened photo, holds circled in the colour of their type.
- **Route list** filterable by sector, scale and difficulty range.
- **Ticks**: “I sent this route”, with the grade you suggest; you can then change it or remove your tick.
- **Consensus grade**: the displayed grade is the one shared by the most climbers who sent the route (see the rules below).
- **Editing** a route by its creator, following the rules below.
- **Deletion** by the creator or an admin, with a safeguard for routes already sent.
- **My account** (click on your username): change your email and password (the username cannot be changed), list of your ticks and routes.
- **Administration** (admins only):
  - **Settings**: security (“Always use HTTPS”), name and logo of the gym or club (shown in the header and on the printable sheet), SMTP server, test email, sending a message to all users or to admins only;
  - **Users**: make or remove an admin, disable or re-enable an account, delete an account;
  - **Photos**: photos that no route uses (left in their sector after a route was deleted or its photo changed), with their sector, and permanent deletion;
  - **Printable sheet**: A4 poster with the club logo and a QR code to the site;
  - **Updates**: alert when a new version is published, and one-click update.

## Business rules

| Situation | Creator | Other climber | Admin |
|---|---|---|---|
| Route that **nobody else** has sent | edits **everything**: photo, holds, grade, name, description | — | — |
| Route **sent** by at least one other climber | edits **only the name and description** | — | — |
| Delete a route not yet sent | simple confirmation | — | simple confirmation |
| Delete a sent route | must type **`DELETE`** (in the interface language) | — | must type **`DELETE`** |
| Add / remove one's tick | yes | yes | yes |

Details:

- The **creator's** tick on their own route does not lock it: only another climber's tick does.
- If all the other climbers' ticks are removed, the route becomes fully editable again.
- Deleting a route also deletes its holds and ticks.
- The editing rules are in `src/Security/VoieVoter.php`. The server checks all these rules, not only the interface.

### Grading

- The **creator** suggests a grade when creating the route. It is shown as long as nobody else has sent it.
- Each climber who **validates** the route (adds their tick) suggests their grade, which they can later change on the route page.
- As soon as there is at least one suggestion from a validator, only these suggestions count: the displayed grade is **the most suggested one**.
- **Tie**: the creator's suggestion breaks the tie if it is among the tied grades; otherwise the **median** of the tied grades is taken (with an even number, the easier of the two middle ones).
- The creator's tick does not vote. The list, sorting and filters use the displayed grade.
- The rule is in `src/Cotation/ConsensusCotation.php` (tested by `tests/Cotation/ConsensusCotationTest.php`).

### Accounts

- A **disabled** account can no longer log in; this is reversible. Its current session is closed immediately.
- A **deleted** account loses its email and password (irreversible). Its username stays reserved and shown on its routes and ticks, which remain visible and keep counting; only an admin can delete its routes.
- There is always at least **one active administrator**: the last one can be neither demoted, disabled nor deleted.
- When an account's role changes, its session is closed: the user must log in again (rights are read at login).

### Personal data (GDPR)

To be reused in the club's privacy page, if there is one:

- **Data kept** for each account: email, username, password (hashed,
  never in clear text), sign-up date, status (admin, active), interface language.
  The application keeps neither IP addresses nor login history; the web server
  keeps its own access logs.
- **Published content**: routes (photo, holds, name, description), ticks and
  suggested grades, shown under the username.
- **Cookies**: login session, the “Stay logged in” cookie (30 days)
  only if the box is ticked, and the chosen interface language. No analytics or
  third-party cookies.
- **Deleting an account**: done by an administrator, at the person's request
  (there is no delete button in “My account”). The email
  and password are erased. The **username stays visible** on routes and
  ticks, which remain visible and keep counting towards the grade: they
  are contributions to a shared guidebook. If the person also asks for their
  removal, an administrator deletes their routes one by one.

## Installing with the web wizard

This is the simplest way to install GrimPanBloc, and it also suits
shared hosting without SSH access. The wizard is located in
`public/setup/` and opens in a browser. It is in French.

### Getting the archive

Download **`grimpanbloc-vX.Y.Z.zip`** from the
[releases page](https://github.com/johnburnout/GrimPanBloc/releases/latest).
It contains everything needed, including `vendor/`: the wizard does not run
Composer. The archive's checksum is provided next to it (`.sha256`).

Upload the contents of the `grimpanbloc/` folder to the server, then point the
web root to the **`public/`** folder.

> **Hidden files**: `.env`, `public/.htaccess` and `public/uploads/.htaccess`
> start with a dot, and many FTP clients ignore them by default
> (FileZilla: *Server → Force showing hidden files*). The wizard
> recreates them if they are missing; still check that they are on the server.

The requirements are the same as for the
manual installation: PHP 8.4.1 or later, MariaDB 11.8, Apache with
`mod_rewrite`, and the PHP `zip` extension for one-click updates.

> **PHP settings on shared hosting**: without access to `php.ini`, set
> these limits in the host's control panel (often *PHP configuration*), without
> leaving “Default”, whose value is unknown (often 128M for memory):
>
> | Setting | Value | Why |
> |---|---|---|
> | Maximum memory (`memory_limit`) | **256M** | GD decodes whole photos to rotate and reduce them; 256M covers the 25-megapixel limit |
> | Maximum upload size (`upload_max_filesize`) | 16M or more | photos up to 15 MB |
> | Maximum HTTP POST size (`post_max_size`) | 20M or more | the photo plus the rest of the form |
>
> The wizard reports insufficient memory, but it is deleted after
> installation: check again if the host changes its settings.

To build the archive yourself from the code: `bin/construire-release.sh`
(see [Publishing a new version](#publishing-a-new-version)).

### Running the wizard

Open `https://your-domain/setup/`. As long as GrimPanBloc is not installed, the
site's home page redirects there by itself.

| Step | What the wizard does |
|---|---|
| 1. Requirements | Checks PHP, the extensions, write permissions and upload limits. Red items block, orange ones are advice. |
| 2. Database | Tests the MariaDB connection, creates the database if needed, then writes `.env.local` (`APP_ENV=prod`, random `APP_SECRET`, `DATABASE_URL`). If PHP is not allowed to create this file, it shows its contents so you can create it yourself. |
| 3. Tables | Runs the Doctrine migrations. |
| 4. Administrator | Creates the first account, with the administrator role. |
| 5. Finalisation | Compiles the logos, warms up the cache, **locks itself and then deletes the `public/setup/` folder**. |

### Wizard security

- **Run the installation right after uploading the files.** Before step 2,
  any visitor could open the wizard.
- From step 2 on, the installation is **reserved for the browser that started it**
  (file `var/setup-en-cours.lock`). Another browser gets a 403 error.
- Once the installation is finished, `var/installation.lock` blocks the wizard
  **permanently**, even if the `public/setup/` folder comes back (after a
  `git pull`, for example).
- On a server installed by hand (with an existing `.env.local`),
  the wizard refuses to work as well.
- If the web server is not allowed to delete `public/setup/`, the wizard
  says so: **delete the folder yourself**.

**To start an installation again** (after an error or a closed browser),
delete `.env.local`, `var/setup-en-cours.lock` and
`var/installation.lock` on the server, put the `public/setup/` folder back if needed, then
reopen `/setup/`.

## Manual installation on an Apache server

For a server where you have SSH access and control over Apache (VPS, dedicated server).
This method does not use the wizard: delete the `public/setup/` folder after
installation.

The commands below target **Debian 13 (Trixie)**, which directly provides
PHP 8.4 and MariaDB 11.8. On Ubuntu or an older Debian, add
Ondřej Surý's PHP repository (PHP 8.4) and the official MariaDB repository (11.8);
the rest is identical.

Conventions used (adapt them):

| | Value |
|---|---|
| Domain | `grimpanbloc.example.org` |
| Application folder | `/var/www/grimpanbloc` |
| User who deploys | `deploy` |
| Apache and PHP-FPM user | `www-data` |

> **Shared hosting** (no access to the Apache configuration): see
> [the variant at the end of this section](#variant-shared-hosting).

### 1. Packages

```bash
sudo apt update
sudo apt install apache2 mariadb-server git unzip composer \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-gd php8.4-intl \
  php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip
```

The `exif` extension, needed to rotate photos, is included in
`php8.4-common` on Debian. To check:

```bash
php -m | grep -E "pdo_mysql|gd|exif|intl"
```

### 2. Apache and PHP-FPM

Apache passes `.php` files to PHP-FPM, with the *event* MPM. There is no
`mod_php`.

```bash
sudo a2dismod php8.4 mpm_prefork 2>/dev/null   # if mod_php was installed
sudo a2enmod mpm_event proxy_fcgi setenvif rewrite headers
sudo a2enconf php8.4-fpm
sudo systemctl restart php8.4-fpm apache2
```

### 3. PHP settings

The form accepts photos up to 15 MB, but PHP limits them to 2 MB by
default. Create `/etc/php/8.4/fpm/conf.d/90-grimpanbloc.ini`:

```ini
upload_max_filesize = 16M
post_max_size = 20M
memory_limit = 256M        ; rotating large photos (GD), up to 25 Mpx

; Production performance
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0   ; clear the cache when deploying (see “Updating”)
realpath_cache_size = 4096K
realpath_cache_ttl = 600
```

```bash
sudo systemctl reload php8.4-fpm
```

### 4. Database

```bash
sudo mariadb
```

```sql
CREATE DATABASE grimpanbloc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'grimpanbloc'@'localhost' IDENTIFIED BY 'strong-password';
GRANT ALL PRIVILEGES ON grimpanbloc.* TO 'grimpanbloc'@'localhost';
FLUSH PRIVILEGES;
```

If the password contains special characters (`@`, `:`, `/`, `#`…), it
must be encoded in the connection URL (for example `@` becomes `%40`).

### 5. Application code

```bash
sudo mkdir -p /var/www/grimpanbloc
sudo chown deploy:www-data /var/www/grimpanbloc
git clone https://github.com/johnburnout/GrimPanBloc.git /var/www/grimpanbloc
cd /var/www/grimpanbloc
```

Create `/var/www/grimpanbloc/.env.local`. This file is **not versioned** and
contains the secrets:

```bash
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<output of: openssl rand -hex 32>
DATABASE_URL="mysql://grimpanbloc:strong-password@127.0.0.1:3306/grimpanbloc?serverVersion=11.8.6-MariaDB&charset=utf8mb4"
```

```bash
chmod 640 .env.local
```

Then install and initialise the application:

```bash
composer install --no-dev --optimize-autoloader
composer dump-env prod                          # compiles .env* into .env.local.php (faster)
php bin/console doctrine:migrations:migrate -n
php bin/console asset-map:compile               # logos and favicon (assets/ folder)
php bin/console cache:clear
```

### 6. File permissions

PHP-FPM (`www-data`) must be able to write to `var/` (cache, logs) and to the
photo folder. It does not need to write anywhere else.

```bash
cd /var/www/grimpanbloc
sudo chown -R deploy:www-data .
sudo find . -type d -exec chmod 750 {} +
sudo find . -type f -exec chmod 640 {} +
sudo chmod 750 bin/console
sudo chmod -R g+w var public/uploads/photos
sudo find var public/uploads/photos -type d -exec chmod g+s {} +   # new files in the www-data group
```

> The `public/uploads/photos/` folder contains the uploaded photos. They
> are **not versioned**: this folder must be **kept** on every
> deployment and **backed up** together with the database.

### 7. VirtualHost

Create `/etc/apache2/sites-available/grimpanbloc.conf`:

```apache
<VirtualHost *:80>
    ServerName grimpanbloc.example.org
    DocumentRoot /var/www/grimpanbloc/public

    <Directory /var/www/grimpanbloc/public>
        DirectoryIndex index.php
        # Full configuration here: .htaccess files are not read (faster)
        AllowOverride None
        Require all granted
        FallbackResource /index.php
    </Directory>

    # Photo folder: images only, no scripts
    <Directory /var/www/grimpanbloc/public/uploads>
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

    # Long-term browser cache for files versioned by AssetMapper
    <Directory /var/www/grimpanbloc/public/assets>
        Header set Cache-Control "public, max-age=31536000, immutable"
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/grimpanbloc_error.log
    CustomLog ${APACHE_LOG_DIR}/grimpanbloc_access.log combined
</VirtualHost>
```

```bash
sudo a2ensite grimpanbloc
sudo a2dissite 000-default
sudo apache2ctl configtest
sudo systemctl reload apache2
```

### 8. HTTPS

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d grimpanbloc.example.org
```

Certbot creates the `*:443` VirtualHost, adds the HTTP → HTTPS redirection and
schedules the automatic renewal of the certificate.

### Mandatory HTTPS

Once the certificate is in place (Certbot, or the host's automatic HTTPS), open
**Administration → Settings** over `https://` and tick **“Always use HTTPS”**:
visitors arriving on `http://` are redirected, and browsers remember the
choice for 30 days (HSTS header). The setting can only be enabled from a page over
HTTPS: if PHP did not detect HTTPS (misconfigured proxy), the redirection
would loop.

### 9. Checking

- https://grimpanbloc.example.org shows the list of routes;
- create an account, a sector, then a route with a photo of several MB;
- in case of error: `/var/log/apache2/grimpanbloc_error.log` and
  `/var/www/grimpanbloc/var/log/prod.log`.

### Variant: shared hosting

Without access to the Apache configuration, the project provides two
`.htaccess` files that play the same role as the VirtualHost:

| File | Role |
|---|---|
| `public/.htaccess` | Sends all requests to `index.php` (`symfony/apache-pack` package) |
| `public/uploads/.htaccess` | Only allows images in the photo folder |

The host must offer **`mod_rewrite`** and **`AllowOverride All`**, and
the **web root must point to the `public/` folder**. If that is not possible,
do **not** publish the whole project: `.env.local`, `var/` and `vendor/` must
never be reachable from the web.

The commands of step 5 are then run over SSH, or locally before uploading
the files. The upload limits of step 3 are set in the host's
control panel or in a `.user.ini` file.

### Other web server: nginx

GrimPanBloc is tested with **Apache** only. Under nginx, the
`.htaccess` files are **ignored**: their two roles must be reproduced in the
site configuration.

1. **Send requests to `public/index.php`**, as for any Symfony
   application (see the [Symfony documentation on nginx](https://symfony.com/doc/current/setup/web_server_configuration.html#nginx)).
2. **Protect `public/uploads/`**: serve only images there, and never
   run PHP there. Without this rule, the security of the photos relies on the
   application alone (random file name, extension taken from the image's real
   type), which holds, but without a second barrier.

Example to adapt, **not tested by the project**:

```nginx
root /var/www/grimpanbloc/public;
client_max_body_size 20M;

location / {
    try_files $uri /index.php$is_args$args;
}

# Photos and logo: images only, never PHP
location ^~ /uploads/ {
    location ~* \.(jpe?g|png|webp)$ {
        add_header X-Content-Type-Options nosniff always;
    }
    return 403;
}

location ~ ^/index\.php(/|$) {
    fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    fastcgi_split_path_info ^(.+\.php)(/.*)$;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_param DOCUMENT_ROOT $realpath_root;
    internal;
}

# No other PHP file is executed (installation wizard included)
location ~ \.php$ {
    return 404;
}
```

The web installation wizard (`public/setup/`) is designed for Apache: under
nginx, follow the manual installation.

## Updating the server

GrimPanBloc checks **once a day** whether a new release has been published
on GitHub. If so, administrators see a banner at the top of the
pages, and the details are on the **Updates** page (`/admin/mise-a-jour`).

> **Before any update, back up the database** (the host's tool, or
> `mariadb-dump -u grimpanbloc -p grimpanbloc > grimpanbloc-$(date +%F).sql`).
> The code itself is backed up automatically.

### In one click, from the administration

On the **Updates** page, the “Update” button:

1. downloads the release archive and verifies its **SHA-256 checksum**;
2. checks its contents, then puts the site **into maintenance** (503 page for visitors);
3. replaces the code. The old code is moved to `var/mise-a-jour/precedente-X.Y.Z-…/`;
4. with the new code: clears the cache, runs the database **migrations**,
   recompiles the logos, then **ends the maintenance**.

**Never touched**: `.env.local`, `var/` and the photos
(`public/uploads/photos/`). The `public/setup/` wizard is not reinstalled.

This method assumes that the web server (PHP) can write to the project
root, to `public/` and to `var/`: this is usually the case on
shared hosting. Otherwise, the page says so and suggests the SSH command.

If GitHub's API refuses the check (limit of 60 requests per hour per address,
often reached on shared hosting), GrimPanBloc reads the latest
version directly on github.com.

### Trusting GitHub releases

Updates, in one click as over SSH, install what is published in the
[releases of the GitHub repository](https://github.com/johnburnout/GrimPanBloc/releases).
The SHA-256 checksum is published **in the same release** as the archive: it
guarantees that the archive was not damaged on the way, **not** that it really comes
from the maintainers. In other words, anyone who can publish a release on this repository
can get their code installed on every site that updates.

In practice:

- on the maintainers' side: GitHub account protected by two-factor authentication,
  write access to the repository reserved for a few trusted people;
- on the site's side: updating is **never automatic**, an administrator must
  click. If in doubt about a release (unexpected version, strange
  release notes), do not install it and ask around.

Stronger verification (signed archives, GitHub attestations)
could be added in the future.

### Over SSH

```bash
cd /var/www/grimpanbloc
php bin/console app:mise-a-jour --verifier   # is there a new version?
php bin/console app:mise-a-jour              # install it (same mechanism as one click)
sudo systemctl reload php8.4-fpm             # clears OPcache (validate_timestamps = 0)
```

Run the command as the user who owns the files (`deploy` in the
manual installation).

### Going back to the previous version

The old version stays in `var/mise-a-jour/precedente-X.Y.Z-…/`, which
contains all the replaced files. To go back, put these
items back at the root, restore the database backup if a migration was
applied, then clear the cache (`rm -rf var/cache/prod`).

### If an update is interrupted

Maintenance mode (`var/maintenance.lock`) ends by itself after
**30 minutes**. To end it right away, delete this file.

### Installation made with git (without the archive)

```bash
cd /var/www/grimpanbloc
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate -n
php bin/console asset-map:compile
php bin/console cache:clear
sudo systemctl reload php8.4-fpm
```

### Notes for specific versions

- **Photo reduction**: after the update that introduces it, run
  `php bin/console app:photos:reduire` once to reduce the photos already uploaded.
  Start with `--simulation` to see what will be done.
- **Languages** (2.0.0): the update adds a column to user accounts
  (chosen language). The messages shown during the update from a 1.x version
  stay in French.
- **Photo thumbnails** (1.3.11): the update creates those of the photos already
  uploaded, once the site has reopened. If it reports a problem, finish over SSH with
  `php bin/console app:photos:miniatures`. Meanwhile, the lists show
  the full photos.

## Publishing a new version

Versions follow [SemVer](https://semver.org/): `MAJOR.MINOR.PATCH`.

1. Update the **`VERSION`** file (for example `1.1.0`).
2. Add a **`## [1.1.0] - YYYY-MM-DD`** section at the top of `CHANGELOG.md`:
   it becomes the release notes, which administrators read before
   updating (the changelog is written in French).
3. Commit, create the tag and push:

   ```bash
   git commit -am "Version 1.1.0"
   git tag -a v1.1.0 -m "GrimPanBloc 1.1.0"
   git push && git push origin v1.1.0
   ```

The GitHub Actions workflow `.github/workflows/release.yml` does the rest:

- it checks that the tag matches the `VERSION` file;
- it builds the archive `grimpanbloc-v1.1.0.zip` (code, `vendor/` without the
  dev packages, JavaScript packages) and its `.sha256` checksum, with
  `bin/construire-release.sh`;
- it also publishes a copy under the application's former name,
  `grimpepan-v1.1.0.zip`: installations older than
  1.3.0 look for it to update themselves;
- it publishes the release.

Installed sites then offer it to their administrators, within
24 hours at most.

To build the archive locally (to test it, for example):
`bin/construire-release.sh build/`.

> **Contract between versions**: the route `/admin/mise-a-jour/finaliser` and its
> `jeton` parameter must exist in every version. It is the
> **new** version that receives this request, right after the code is replaced.

## Development setup (macOS)

In development, the application runs with the Symfony CLI local server,
without Apache. The `.htaccess` files are only used in production.

### Requirements

- PHP ≥ 8.4 with the `pdo_mysql`, `gd`, `exif` and `intl` extensions
- Composer 2
- Symfony CLI (local server)
- MariaDB 11.8 (see [below](#local-mariadb-database))

```bash
brew install php composer
brew install symfony-cli/tap/symfony-cli
symfony check:requirements
```

### Getting started

```bash
git clone https://github.com/johnburnout/GrimPanBloc.git
cd GrimPanBloc
composer install

# Database (MariaDB must run on port 3307, see below)
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n

# Local server in the background
symfony serve -d
symfony open:local
```

Then create an account, a sector, then a route.

In development, `APP_SECRET` is provided by `.env.dev`. To override a
variable on your machine (database credentials, for example), use
`.env.local`, which is not versioned.

### Local HTTPS (optional)

```bash
symfony server:ca:install     # asks for the macOS password
symfony server:stop && symfony serve -d
```

The application is then served on https://127.0.0.1:8000.

### Size of uploaded photos

The form accepts photos up to **15 MB**, but PHP limits uploads
to 2 MB by default. The `php.ini` file at the root of the project raises these limits
(`upload_max_filesize = 16M`, `post_max_size = 20M`, `memory_limit = 256M`). The
Symfony CLI reads it automatically: just restart the server after
changing it (`symfony server:stop && symfony serve -d`).

This file is only used in development. On the server, see
[step 3](#3-php-settings) of the Apache installation.

## Local MariaDB database

Production runs on **MariaDB 11.8.6**. Development therefore uses
MariaDB 11.8, not MySQL: Doctrine migrations depend on the platform,
and Doctrine cannot generate a MariaDB migration from a MySQL server.

On the development machine, MariaDB runs **alongside** an existing MySQL
(port 3306):

| | Value |
|---|---|
| Package | `mariadb@11.8` (Homebrew, keg-only) |
| Address | `127.0.0.1:3307`, user `root` without password |
| Data | `/opt/homebrew/var/mariadb-11.8` |
| Start-up | LaunchAgent `~/Library/LaunchAgents/local.mariadb-11.8.plist` |
| Database | `grimpepan` |

> ⚠️ **Do not use `brew services start mariadb@11.8`**: this service
> would start MariaDB on `/opt/homebrew/var/mysql`, MySQL's data folder,
> and could damage it.

Installing from scratch:

```bash
brew install mariadb@11.8
M=/opt/homebrew/opt/mariadb@11.8
mkdir -p /opt/homebrew/var/mariadb-11.8
$M/bin/mariadb-install-db --no-defaults --basedir=$M \
  --datadir=/opt/homebrew/var/mariadb-11.8 \
  --auth-root-authentication-method=normal --skip-test-db
```

The LaunchAgent runs `mariadbd --no-defaults --port=3307 --bind-address=127.0.0.1
--socket=/tmp/mariadb-3307.sock --datadir=/opt/homebrew/var/mariadb-11.8
--character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci`.

```bash
# start / stop
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/local.mariadb-11.8.plist
launchctl bootout  gui/$(id -u)/local.mariadb-11.8

# console
/opt/homebrew/opt/mariadb@11.8/bin/mariadb -h127.0.0.1 -P3307 -uroot grimpepan
```

The default connection is in `.env`:

```
DATABASE_URL="mysql://root@127.0.0.1:3307/grimpepan?serverVersion=11.8.6-MariaDB&charset=utf8mb4"
```

**With Docker**, `compose.yaml` also provides a `mariadb:11.8.6` container.
With `docker compose up -d`, the Symfony CLI detects the container and provides
the right `DATABASE_URL` itself.

## Useful commands

```bash
# Server
symfony serve -d               # start in the background
symfony server:log             # follow the logs
symfony server:stop            # stop

# Database
php bin/console make:migration                 # after changing an entity
php bin/console doctrine:migrations:migrate -n # apply the migrations
php bin/console doctrine:schema:validate       # check mapping and schema

# Photos
php bin/console app:photos:reduire --simulation  # lists photos larger than 2000 px
php bin/console app:photos:reduire               # reduces them (files and dimensions in the database)
php bin/console app:photos:miniatures            # creates missing thumbnails (--toutes: recreates them all)

# Checks
php bin/console lint:twig templates
php bin/console lint:container
php bin/console debug:router
php bin/console debug:translation en --only-missing   # untranslated texts
```

**Creating an administrator account** (the password is asked for without being shown):

```bash
php bin/console app:utilisateur:creer admin@example.org Admin --admin
```

To give the role to an existing account: **Administration → Users → Make admin**. The person must log in again.

## Project structure

| Location | Contents |
|---|---|
| `src/Entity` | `User`, `Secteur`, `Photo`, `Voie`, `Prise`, `Croix` |
| `src/Enum` | `CotationFr`, `CotationCouleur`, `SystemeCotation`, `TypePrise` |
| `src/Controller` | Home, sign-up, login, sectors, routes (creation, editing, ticks, deletion) |
| `src/Form` | `RegistrationFormType`, `SecteurType`, `VoieType` (`trace` option: full form or name + description) |
| `src/Security/VoieVoter.php` | `VOIE_MODIFIER` and `VOIE_MODIFIER_TRACE` permissions |
| `src/Service/PhotoUploader.php` | Saving uploaded photos |
| `src/Service/PhotoOptimiseur.php` | EXIF rotation and reduction to 2000 × 2000 px |
| `src/Command/ReduirePhotosCommand.php` | `app:photos:reduire` command (photos already saved) |
| `src/Service/MiniaturesPhotos.php` | 800 px thumbnails (`public/uploads/photos/miniatures/`), Twig function `miniature()` |
| `src/Command/MiniaturesPhotosCommand.php` | `app:photos:miniatures` command (missing thumbnails) |
| `templates/voie/_form.html.twig` | Full form, shared by creation and editing |
| `templates/voie/_photo.html.twig` | Photo + holds rendering (SVG) |
| `public/js/editeur.js` | Hold editor |
| `public/css/app.css` | Styles (palette matching the logo) |
| `public/fonts/bricolage-grotesque/` | Font for headings and the logo, with its licence |
| `assets/logo/` | SVG logos and favicon |
| `public/drapeaux/` | Flags of the language menu (SVG) |
| `translations/` | Interface texts in five languages (`messages+intl-icu`, `validators`, `security`) |
| `src/Locale/Langues.php`, `src/EventSubscriber/LangueListener.php` | Choosing the interface language (cookie, browser, account) |
| `public/setup/` | Web installation wizard (deleted after installation) |
| `src/Command/CreerUtilisateurCommand.php` | `app:utilisateur:creer` command (administrator account…) |
| `src/MiseAJour/` | Checking GitHub releases, installing an update, maintenance mode |
| `src/Cotation/ConsensusCotation.php` | Displayed grade computed from the validators' suggestions |
| `src/Controller/ProfilController.php` | My account: profile, my ticks, my routes |
| `src/Controller/AdminReglagesController.php` | Settings (club, SMTP, test email, group mailing) and printable sheet |
| `src/Controller/AdminUtilisateursController.php` | User list: admin, disabling, deletion |
| `src/Mail/` | SMTP settings (password encrypted with `APP_SECRET`) and sending emails |
| `src/Site/IdentiteClub.php` | Club name and logo (`public/uploads/logo/`), kept in cache |
| `src/Site/ReglageHttps.php` | “Always use HTTPS” setting, kept in cache (applied by `ForcerHttpsSubscriber`) |
| `src/Security/UserChecker.php` | Refuses login for disabled accounts |
| `tests/` | PHPUnit tests (`php bin/phpunit`) |
| `phpstan.dist.neon` | Static analysis (`vendor/bin/phpstan analyse`), level 6, no error tolerated |
| `.php-cs-fixer.dist.php` | Symfony code style (`vendor/bin/php-cs-fixer fix`) |
| `src/Controller/AdminMiseAJourController.php` | “Updates” page (`/admin/mise-a-jour`) |
| `src/Command/MiseAJourCommand.php` | `app:mise-a-jour` command |
| `VERSION`, `CHANGELOG.md` | Installed version and changelog |
| `bin/construire-release.sh` | Building the release archive |
| `.github/workflows/release.yml` | Automatic publication of a release for each `vX.Y.Z` tag |
| `migrations/` | Doctrine migrations (generated on MariaDB) |
| `public/.htaccess` | URL rewriting for Apache without a VirtualHost (`symfony/apache-pack`) |
| `public/uploads/.htaccess` | Only allows images in the photo folder |
| `php.ini` | PHP limits of the development server (photos up to 16 MB) |
| `compose.yaml` | Optional Docker containers (MariaDB, Mailpit) |

## Technical choices

- **Photos reduced to 2000 × 2000 px** (JPEG quality 85, progressive; PNG and WebP
  kept in their format). A photo that is already small enough is not saved again,
  so not recompressed. As holds are stored as percentages, a reduction does not
  shift them.
- **800 px thumbnails** in `uploads/photos/miniatures/`, under the same name as
  the photo: lists load a few dozen KB per route instead of the
  full photo. A photo without a thumbnail (smaller than 800 px, or thumbnail
  not created yet) is shown in full: nothing breaks if creation fails.
- **Holds as percentages**: x, y and radius are stored as a % of the image, to
  stay aligned whatever the display size.
- **Grade** stored as text (`6a`, `bleu`) with a numeric **rank** to
  sort and filter by difficulty, whatever the scale.
- **No JavaScript build**: the editor is in plain JavaScript, served from `public/js/`.
- **CSRF**: Symfony 8 “stateless” protection, which checks the
  `Origin` header sent by the browser.
- **Validated route**: `Voie::estValidee()` is true as soon as a tick comes from a
  climber other than the author.
- **Translations**: keys in `translations/` (ICU format, with plural forms
  for each language); flash messages are `TranslatableMessage` objects and
  service errors are translatable exceptions (`src/Exception/`).

## Visual identity

| File (`assets/logo/`) | Use |
|---|---|
| `logo-inverse.svg` | Header (dark background) |
| `mark-inverse.svg` | Header on small screens (≤ 480 px) |
| `favicon.svg` | Tab icon |
| `logo.svg`, `mark.svg`, `mark-mono.svg` | Light backgrounds, printing |

- **Colours**: dark `#1C1F24`, orange `#E8622C`, light `#F4F2EE`.
- **Font**: *Bricolage Grotesque* (variable, weights 200 to 800), self-hosted,
  without calling Google Fonts. It is used for the logo and headings. Body
  text stays in the system font.
- The header logo is inserted as *inline* SVG (`{{ source('@logo/…') }}`):
  loaded as an `<img>`, it could not use the page's font.

## Next steps

- Several routes shown on the same photo
- Grade opinions, comments
- Administration: role management, moderation, archiving of dismantled routes

## Licence

Code: GNU GPL v3 or later (`GPL-3.0-or-later`), see [`LICENSE`](LICENSE).
Bricolage Grotesque font: SIL Open Font License 1.1, see
[`public/fonts/bricolage-grotesque/LICENSE.txt`](public/fonts/bricolage-grotesque/LICENSE.txt).
