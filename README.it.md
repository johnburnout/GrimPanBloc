<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="assets/logo/logo-inverse.svg">
    <img src="assets/logo/logo.svg" alt="GrimPanBloc" width="330">
  </picture>
</p>

<p align="center">
  <a href="README.md">Français</a> · <a href="README.en.md">English</a> · <a href="README.es.md">Español</a> · <strong>Italiano</strong> · <a href="README.ru.md">Русский</a>
</p>

# GrimPanBloc

Creazione e visualizzazione di blocchi e vie su una struttura artificiale
d'arrampicata di tipo «pannello».

Si fotografa una zona del muro, si segnano le prese della via sulla foto, le si
dà un grado, e gli altri climber possono consultarla e poi segnarla come chiusa.

**Tecnologie**: Symfony 8.1 · PHP ≥ 8.4.1 · Twig · Doctrine ORM · MariaDB 11.8 ·
JavaScript nativo (nessuna fase di build) · AssetMapper.

> Il [README](README.md) in francese è la versione di riferimento; questa traduzione
> può essere leggermente indietro.

---

## Indice

- [Funzionalità](#funzionalità)
- [Regole di funzionamento](#regole-di-funzionamento)
- [Installazione con la procedura guidata web](#installazione-con-la-procedura-guidata-web)
- [Installazione manuale su un server Apache](#installazione-manuale-su-un-server-apache)
- [Aggiornamento del server](#aggiornamento-del-server)
- [Pubblicare una nuova versione](#pubblicare-una-nuova-versione)
- [Installazione per lo sviluppo (macOS)](#installazione-per-lo-sviluppo-macos)
- [Database MariaDB locale](#database-mariadb-locale)
- [Comandi utili](#comandi-utili)
- [Struttura del progetto](#struttura-del-progetto)
- [Scelte tecniche](#scelte-tecniche)
- [Identità visiva](#identità-visiva)
- [Prossimi passi](#prossimi-passi)
- [Licenza](#licenza)

---

## Funzionalità

- **Lingue**: francese, inglese, spagnolo, italiano e russo. Viene usata d'ufficio la lingua
  del browser; il menu a piè di pagina permette di cambiarla (la scelta viene ricordata e
  salvata nell'account del climber connesso). I testi sono in `translations/`
  (formato ICU per i plurali); la procedura di installazione e i comandi restano in francese.
- **Account**: registrazione (email, nome utente, password), accesso con «ricordami», uscita.
- **Settori**: zone del muro (pannello, strapiombo, placca…), ognuna con le sue foto e le sue vie.
- **Creazione di una via**:
  - caricamento di una foto (JPEG, PNG o WebP) o riutilizzo di una foto del settore.
    La foto viene raddrizzata secondo i dati EXIF e ridotta a **2000 × 2000 px al massimo**,
    mantenendo le proporzioni, per risparmiare spazio su disco. Una **miniatura**
    (800 px di lato al massimo) viene usata negli elenchi delle vie e nell'amministrazione;
  - marcatura delle prese sulla foto: partenza, mano, piede, top;
  - grado francese (3a → 9c) o colore (verde → bianco).
- **Editor delle prese** (touch e mouse):
  - toccare la foto posiziona una presa;
  - toccare una presa ne cambia il tipo, trascinarla la sposta;
  - la gomma cancella una presa, «Annulla» torna indietro;
  - il cursore «Dimensione» regola le prossime prese **e l'ultima posizionata o spostata**.
- **Visualizzazione di una via**: foto scurita, prese cerchiate del colore del loro tipo.
- **Elenco delle vie** filtrabile per settore, scala e intervallo di difficoltà.
- **Chiusure**: «Ho chiuso questa via», con il grado che si propone; poi si può modificarlo o togliere la chiusura.
- **Grado per consenso**: il grado mostrato è quello che riunisce più climber che hanno chiuso la via (vedi le regole sotto).
- **Modifica** di una via da parte del suo creatore, secondo le regole sotto.
- **Eliminazione** da parte del creatore o di un amministratore, con una protezione per le vie già chiuse.
- **Il mio account** (clic sul proprio nome utente): modificare email e password (il nome utente non è modificabile), elenco delle proprie chiusure e vie.
- **Amministrazione** (riservata agli amministratori):
  - **Impostazioni**: sicurezza («Usa sempre HTTPS»), nome e logo della palestra o del club (mostrati nell'intestazione e sulla scheda da stampare), server SMTP, email di prova, invio di un messaggio a tutti gli utenti o ai soli amministratori;
  - **Utenti**: nominare o togliere un amministratore, disattivare o riattivare un account, eliminare un account;
  - **Foto**: foto non usate da nessuna via (rimaste nel loro settore dopo l'eliminazione di una via o un cambio di foto), con il loro settore, ed eliminazione definitiva;
  - **Scheda da stampare**: locandina A4 con il logo del club e un codice QR verso il sito;
  - **Aggiornamenti**: avviso quando viene pubblicata una nuova versione, e aggiornamento con un clic.

## Regole di funzionamento

| Situazione | Creatore | Altro climber | Amministratore |
|---|---|---|---|
| Via che **nessun altro** ha chiuso | modifica **tutto**: foto, prese, grado, nome, descrizione | — | — |
| Via **chiusa** da almeno un altro climber | modifica **solo il nome e la descrizione** | — | — |
| Eliminare una via non chiusa | semplice conferma | — | semplice conferma |
| Eliminare una via chiusa | deve digitare **`ELIMINA`** (nella lingua dell'interfaccia) | — | deve digitare **`ELIMINA`** |
| Segnare / togliere la propria chiusura | sì | sì | sì |

Precisazioni:

- La chiusura del **creatore** sulla propria via non la blocca: solo quella di un altro climber lo fa.
- Se tutte le chiusure degli altri climber vengono tolte, la via torna interamente modificabile.
- Eliminare una via elimina anche le sue prese e le sue chiusure.
- Le regole di modifica sono in `src/Security/VoieVoter.php`. Il server verifica tutte queste regole, non solo l'interfaccia.

### Grado

- Il **creatore** propone un grado creando la via. Viene mostrato finché nessun altro l'ha chiusa.
- Ogni climber che **convalida** la via (segna la chiusura) propone il suo grado, che può modificare in seguito nella scheda della via.
- Appena c'è almeno una proposta di un validatore, contano solo queste proposte: il grado mostrato è **il più proposto**.
- **Parità**: la proposta del creatore decide se è tra i gradi in parità; altrimenti si prende la **mediana** dei gradi in parità (con un numero pari, il più facile dei due centrali).
- La chiusura del creatore non vota. L'elenco, l'ordinamento e i filtri usano il grado mostrato.
- La regola è in `src/Cotation/ConsensusCotation.php` (testata da `tests/Cotation/ConsensusCotationTest.php`).

### Account

- Un account **disattivato** non può più accedere; è reversibile. La sessione in corso viene chiusa subito.
- Un account **eliminato** perde email e password (irreversibile). Il suo nome utente resta riservato e mostrato sulle sue vie e chiusure, che restano visibili e continuano a contare; solo un amministratore può eliminare le sue vie.
- Resta sempre almeno **un amministratore attivo**: l'ultimo non può essere né declassato, né disattivato, né eliminato.
- Quando il ruolo di un account cambia, la sua sessione viene chiusa: deve accedere di nuovo (i permessi vengono letti all'accesso).

### Dati personali (GDPR)

Da riprendere nella pagina sulla privacy del club, se esiste:

- **Dati conservati** per ogni account: email, nome utente, password (con hash,
  mai in chiaro), data di registrazione, stato (amministratore, attivo), lingua dell'interfaccia.
  L'applicazione non conserva né indirizzi IP né cronologia degli accessi; il server web
  tiene i propri log di accesso.
- **Contenuti pubblicati**: vie (foto, prese, nome, descrizione), chiusure e
  gradi proposti, mostrati con il nome utente.
- **Cookie**: sessione di accesso, cookie «Resta connesso» (30 giorni)
  solo se la casella è spuntata, e lingua scelta per l'interfaccia. Nessun cookie di
  misurazione del pubblico né di servizi terzi.
- **Eliminazione di un account**: la fa un amministratore, su richiesta della
  persona (non c'è un pulsante di eliminazione in «Il mio account»). L'email
  e la password vengono cancellate. Il **nome utente resta visibile** sulle vie e
  sulle chiusure, che restano visibili e continuano a contare per il grado: sono
  contributi a una guida comune. Se la persona chiede anche la loro
  rimozione, un amministratore elimina le sue vie una per una.

## Installazione con la procedura guidata web

È il modo più semplice di installare GrimPanBloc, e va bene anche per gli
hosting condivisi senza accesso SSH. La procedura guidata si trova in
`public/setup/` e si apre in un browser. È in francese.

### Scaricare l'archivio

Scarica **`grimpanbloc-vX.Y.Z.zip`** dalla
[pagina delle release](https://github.com/johnburnout/GrimPanBloc/releases/latest).
Contiene tutto il necessario, compreso `vendor/`: la procedura guidata non esegue
Composer. L'impronta dell'archivio è fornita accanto (`.sha256`).

Carica il contenuto della cartella `grimpanbloc/` sul server, poi fai puntare la
radice web sulla cartella **`public/`**.

> **File nascosti**: `.env`, `public/.htaccess` e `public/uploads/.htaccess`
> iniziano con un punto, e molti client FTP li ignorano per impostazione predefinita
> (FileZilla: *Server → Forza la visualizzazione dei file nascosti*). La procedura guidata
> li ricrea se mancano; verifica comunque che siano sul server.

I requisiti sono gli stessi dell'installazione
manuale: PHP 8.4.1 o superiore, MariaDB 11.8, Apache con
`mod_rewrite`, e l'estensione PHP `zip` per gli aggiornamenti con un clic.

> **Impostazioni PHP su un hosting condiviso**: senza accesso a `php.ini`, imposta
> questi limiti nel pannello dell'hosting (spesso *Configurazione PHP*), senza
> lasciare «Predefinito», il cui valore è sconosciuto (spesso 128M di memoria):
>
> | Impostazione | Valore | Perché |
> |---|---|---|
> | Memoria massima (`memory_limit`) | **256M** | GD decodifica le foto intere per raddrizzarle e ridurle; 256M coprono il limite di 25 megapixel |
> | Dimensione massima di caricamento (`upload_max_filesize`) | 16M o più | foto fino a 15 MB |
> | Dimensione massima HTTP POST (`post_max_size`) | 20M o più | la foto più il resto del modulo |
>
> La procedura guidata segnala una memoria insufficiente, ma viene eliminata dopo
> l'installazione: da ricontrollare se l'hosting cambia le sue impostazioni.

Per costruire l'archivio da solo a partire dal codice: `bin/construire-release.sh`
(vedi [Pubblicare una nuova versione](#pubblicare-una-nuova-versione)).

### Avviare la procedura guidata

Apri `https://tuo-dominio/setup/`. Finché GrimPanBloc non è installato, la
home page del sito vi reindirizza da sola.

| Passo | Cosa fa la procedura guidata |
|---|---|
| 1. Requisiti | Verifica PHP, le estensioni, i permessi di scrittura e i limiti di caricamento. I punti rossi bloccano, quelli arancioni sono consigli. |
| 2. Database | Prova la connessione a MariaDB, crea il database se serve, poi scrive `.env.local` (`APP_ENV=prod`, `APP_SECRET` casuale, `DATABASE_URL`). Se PHP non ha il permesso di creare questo file, ne mostra il contenuto perché tu lo crei. |
| 3. Tabelle | Esegue le migrazioni Doctrine. |
| 4. Amministratore | Crea il primo account, con il ruolo di amministratore. |
| 5. Completamento | Compila i loghi, prepara la cache, **si blocca e poi elimina la cartella `public/setup/`**. |

### Sicurezza della procedura guidata

- **Avvia l'installazione subito dopo aver caricato i file.** Prima del passo 2,
  qualsiasi visitatore potrebbe aprire la procedura guidata.
- Dal passo 2, l'installazione è **riservata al browser che l'ha iniziata**
  (file `var/setup-en-cours.lock`). Un altro browser riceve un errore 403.
- Una volta terminata l'installazione, `var/installation.lock` blocca la procedura
  **definitivamente**, anche se la cartella `public/setup/` ricompare (dopo un
  `git pull`, per esempio).
- Su un server installato a mano (con un `.env.local` già presente),
  la procedura guidata si rifiuta anch'essa di funzionare.
- Se il server web non ha il permesso di eliminare `public/setup/`, la procedura
  lo segnala: **elimina tu la cartella**.

**Per ricominciare un'installazione** (dopo un errore o un browser chiuso),
elimina sul server `.env.local`, `var/setup-en-cours.lock` e
`var/installation.lock`, rimetti la cartella `public/setup/` se serve, poi
riapri `/setup/`.

## Installazione manuale su un server Apache

Per un server su cui hai accesso SSH e controllo di Apache (VPS, server dedicato).
Questo metodo non usa la procedura guidata: elimina la cartella `public/setup/` dopo
l'installazione.

I comandi qui sotto sono per **Debian 13 (Trixie)**, che fornisce direttamente
PHP 8.4 e MariaDB 11.8. Su Ubuntu o su una Debian più vecchia, bisogna aggiungere
il repository PHP di Ondřej Surý (PHP 8.4) e il repository ufficiale di MariaDB (11.8);
il resto è identico.

Convenzioni usate (da adattare):

| | Valore |
|---|---|
| Dominio | `grimpanbloc.example.org` |
| Cartella dell'applicazione | `/var/www/grimpanbloc` |
| Utente che esegue il deploy | `deploy` |
| Utente di Apache e PHP-FPM | `www-data` |

> **Hosting condiviso** (nessun accesso alla configurazione di Apache): vedi
> [la variante alla fine di questa sezione](#variante-hosting-condiviso).

### 1. Pacchetti

```bash
sudo apt update
sudo apt install apache2 mariadb-server git unzip composer \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-gd php8.4-intl \
  php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip
```

L'estensione `exif`, necessaria per raddrizzare le foto, è inclusa in
`php8.4-common` su Debian. Per verificare:

```bash
php -m | grep -E "pdo_mysql|gd|exif|intl"
```

### 2. Apache e PHP-FPM

Apache passa i file `.php` a PHP-FPM, con l'MPM *event*. Non c'è
`mod_php`.

```bash
sudo a2dismod php8.4 mpm_prefork 2>/dev/null   # se mod_php era installato
sudo a2enmod mpm_event proxy_fcgi setenvif rewrite headers
sudo a2enconf php8.4-fpm
sudo systemctl restart php8.4-fpm apache2
```

### 3. Impostazioni PHP

Il modulo accetta foto fino a 15 MB, ma PHP le limita per impostazione predefinita a
2 MB. Crea `/etc/php/8.4/fpm/conf.d/90-grimpanbloc.ini`:

```ini
upload_max_filesize = 16M
post_max_size = 20M
memory_limit = 256M        ; raddrizzamento delle foto grandi (GD), fino a 25 Mpx

; Prestazioni in produzione
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0   ; svuotare la cache al deploy (vedi «Aggiornamento»)
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
CREATE USER 'grimpanbloc'@'localhost' IDENTIFIED BY 'password-robusta';
GRANT ALL PRIVILEGES ON grimpanbloc.* TO 'grimpanbloc'@'localhost';
FLUSH PRIVILEGES;
```

Se la password contiene caratteri speciali (`@`, `:`, `/`, `#`…), bisogna
codificarla nell'URL di connessione (per esempio `@` diventa `%40`).

### 5. Codice dell'applicazione

```bash
sudo mkdir -p /var/www/grimpanbloc
sudo chown deploy:www-data /var/www/grimpanbloc
git clone https://github.com/johnburnout/GrimPanBloc.git /var/www/grimpanbloc
cd /var/www/grimpanbloc
```

Crea `/var/www/grimpanbloc/.env.local`. Questo file **non è versionato** e
contiene i segreti:

```bash
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<risultato di: openssl rand -hex 32>
DATABASE_URL="mysql://grimpanbloc:password-robusta@127.0.0.1:3306/grimpanbloc?serverVersion=11.8.6-MariaDB&charset=utf8mb4"
```

```bash
chmod 640 .env.local
```

Poi installa e inizializza l'applicazione:

```bash
composer install --no-dev --optimize-autoloader
composer dump-env prod                          # compila .env* in .env.local.php (più veloce)
php bin/console doctrine:migrations:migrate -n
php bin/console asset-map:compile               # loghi e favicon (cartella assets/)
php bin/console cache:clear
```

### 6. Permessi sui file

PHP-FPM (`www-data`) deve poter scrivere in `var/` (cache, log) e nella
cartella delle foto. Non ha bisogno di scrivere altrove.

```bash
cd /var/www/grimpanbloc
sudo chown -R deploy:www-data .
sudo find . -type d -exec chmod 750 {} +
sudo find . -type f -exec chmod 640 {} +
sudo chmod 750 bin/console
sudo chmod -R g+w var public/uploads/photos
sudo find var public/uploads/photos -type d -exec chmod g+s {} +   # nuovi file nel gruppo www-data
```

> La cartella `public/uploads/photos/` contiene le foto caricate. **Non
> sono versionate**: questa cartella va **conservata** a ogni
> deploy e **salvata** insieme al database.

### 7. VirtualHost

Crea `/etc/apache2/sites-available/grimpanbloc.conf`:

```apache
<VirtualHost *:80>
    ServerName grimpanbloc.example.org
    DocumentRoot /var/www/grimpanbloc/public

    <Directory /var/www/grimpanbloc/public>
        DirectoryIndex index.php
        # Configurazione completa qui: i .htaccess non vengono letti (più veloce)
        AllowOverride None
        Require all granted
        FallbackResource /index.php
    </Directory>

    # Cartella delle foto: solo immagini, nessuno script
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

    # Cache del browser a lungo termine per i file versionati da AssetMapper
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

Certbot crea il VirtualHost `*:443`, aggiunge il reindirizzamento HTTP → HTTPS e
programma il rinnovo automatico del certificato.

### HTTPS obbligatorio

Una volta installato il certificato (Certbot, o HTTPS automatico dell'hosting), apri
**Amministrazione → Impostazioni** in `https://` e spunta **«Usa sempre HTTPS»**:
i visitatori che arrivano in `http://` vengono reindirizzati, e i browser ricordano la
scelta per 30 giorni (intestazione HSTS). L'impostazione si attiva solo da una pagina in
HTTPS: se PHP non rilevasse l'HTTPS (proxy configurato male), il reindirizzamento
andrebbe in loop.

### 9. Verifica

- https://grimpanbloc.example.org mostra l'elenco delle vie;
- crea un account, un settore, poi una via con una foto di diversi MB;
- in caso di errore: `/var/log/apache2/grimpanbloc_error.log` e
  `/var/www/grimpanbloc/var/log/prod.log`.

### Variante: hosting condiviso

Senza accesso alla configurazione di Apache, il progetto fornisce due file
`.htaccess` che svolgono lo stesso ruolo del VirtualHost:

| File | Ruolo |
|---|---|
| `public/.htaccess` | Invia tutte le richieste a `index.php` (pacchetto `symfony/apache-pack`) |
| `public/uploads/.htaccess` | Consente solo le immagini nella cartella delle foto |

L'hosting deve offrire **`mod_rewrite`** e **`AllowOverride All`**, e
la **radice web deve puntare sulla cartella `public/`**. Se non è possibile,
**non** bisogna pubblicare tutto il progetto: `.env.local`, `var/` e `vendor/` non
devono mai essere accessibili dal web.

I comandi del passo 5 si eseguono allora via SSH, o in locale prima di caricare
i file. I limiti di caricamento del passo 3 si impostano nel pannello
dell'hosting o in un `.user.ini`.

### Altro server web: nginx

GrimPanBloc è testato solo con **Apache**. Con nginx, i file
`.htaccess` vengono **ignorati**: i loro due ruoli vanno riprodotti nella
configurazione del sito.

1. **Inviare le richieste a `public/index.php`**, come per qualsiasi applicazione
   Symfony (vedi la [documentazione di Symfony su nginx](https://symfony.com/doc/current/setup/web_server_configuration.html#nginx)).
2. **Proteggere `public/uploads/`**: servire lì solo le immagini, e non
   eseguirvi mai PHP. Senza questa regola, la sicurezza delle foto si basa solo
   sull'applicazione (nome di file casuale, estensione presa dal vero tipo
   dell'immagine), che regge, ma senza una seconda barriera.

Esempio da adattare, **non testato dal progetto**:

```nginx
root /var/www/grimpanbloc/public;
client_max_body_size 20M;

location / {
    try_files $uri /index.php$is_args$args;
}

# Foto e logo: solo immagini, mai PHP
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

# Nessun altro file PHP viene eseguito (procedura di installazione compresa)
location ~ \.php$ {
    return 404;
}
```

La procedura di installazione web (`public/setup/`) è pensata per Apache: con
nginx, segui l'installazione manuale.

## Aggiornamento del server

GrimPanBloc verifica **una volta al giorno** se è stata pubblicata una nuova release
su GitHub. In tal caso, gli amministratori vedono un banner in cima alle
pagine, e il dettaglio si trova nella pagina **Aggiornamenti** (`/admin/mise-a-jour`).

> **Prima di qualsiasi aggiornamento, fai un backup del database** (strumento dell'hosting, o
> `mariadb-dump -u grimpanbloc -p grimpanbloc > grimpanbloc-$(date +%F).sql`).
> Il codice, invece, viene salvato automaticamente.

### Con un clic, dall'amministrazione

Nella pagina **Aggiornamenti**, il pulsante «Aggiorna»:

1. scarica l'archivio della release e ne verifica l'**impronta SHA-256**;
2. ne controlla il contenuto, poi mette il sito **in manutenzione** (pagina 503 per i visitatori);
3. sostituisce il codice. Il vecchio viene spostato in `var/mise-a-jour/precedente-X.Y.Z-…/`;
4. con il nuovo codice: svuota la cache, esegue le **migrazioni** del database,
   ricompila i loghi, poi **toglie la manutenzione**.

**Non vengono mai toccati**: `.env.local`, `var/` e le foto
(`public/uploads/photos/`). La procedura `public/setup/` non viene reinstallata.

Questo metodo presuppone che il server web (PHP) possa scrivere nella radice del
progetto, in `public/` e in `var/`: di solito è così su un
hosting condiviso. Altrimenti, la pagina lo segnala e propone il comando SSH.

Se l'API di GitHub rifiuta la verifica (limite di 60 richieste all'ora per indirizzo,
spesso raggiunto sugli hosting condivisi), GrimPanBloc legge l'ultima
versione direttamente su github.com.

### Fiducia nelle release di GitHub

Gli aggiornamenti, con un clic come via SSH, installano ciò che è pubblicato nelle
[release del repository GitHub](https://github.com/johnburnout/GrimPanBloc/releases).
L'impronta SHA-256 è pubblicata **nella stessa release** dell'archivio: garantisce
che l'archivio non si sia rovinato per strada, **non** che provenga davvero
dai manutentori. In altre parole, chiunque possa pubblicare una release su questo repository
può far installare il proprio codice su tutti i siti che si aggiornano.

In pratica:

- lato manutentori: account GitHub protetto dall'autenticazione a due fattori,
  accesso in scrittura al repository riservato a poche persone di fiducia;
- lato sito: l'aggiornamento non è **mai automatico**, un amministratore deve
  cliccare. In caso di dubbio su una release (versione inattesa, note di versione
  strane), non installarla e informarsi.

In futuro si potrebbe aggiungere una verifica più forte (firma degli archivi,
attestazioni GitHub).

### Via SSH

```bash
cd /var/www/grimpanbloc
php bin/console app:mise-a-jour --verifier   # c'è una nuova versione?
php bin/console app:mise-a-jour              # installarla (stesso meccanismo del clic)
sudo systemctl reload php8.4-fpm             # svuota l'OPcache (validate_timestamps = 0)
```

Esegui il comando con l'utente proprietario dei file (`deploy` nell'installazione
manuale).

### Tornare alla versione precedente

La vecchia versione resta in `var/mise-a-jour/precedente-X.Y.Z-…/`, che
contiene tutti i file sostituiti. Per tornare indietro, rimetti questi
elementi nella radice, ripristina il backup del database se è stata applicata una
migrazione, poi svuota la cache (`rm -rf var/cache/prod`).

### Se un aggiornamento si interrompe

La modalità manutenzione (`var/maintenance.lock`) si toglie da sola dopo
**30 minuti**. Per toglierla subito, elimina questo file.

### Installazione fatta con git (senza archivio)

```bash
cd /var/www/grimpanbloc
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate -n
php bin/console asset-map:compile
php bin/console cache:clear
sudo systemctl reload php8.4-fpm
```

### Note su alcune versioni

- **Riduzione delle foto**: dopo l'aggiornamento che la introduce, esegui una volta
  `php bin/console app:photos:reduire` per ridurre le foto già caricate.
  Comincia con `--simulation` per vedere cosa verrà fatto.
- **Lingue** (2.0.0): l'aggiornamento aggiunge una colonna all'account degli utenti
  (lingua scelta). I messaggi mostrati durante l'aggiornamento da una 1.x
  restano in francese.
- **Miniature delle foto** (1.3.11): l'aggiornamento crea quelle delle foto già
  caricate, una volta riaperto il sito. Se lo segnala, termina via SSH con
  `php bin/console app:photos:miniatures`. Nel frattempo, gli elenchi mostrano
  le foto intere.

## Pubblicare una nuova versione

Le versioni seguono [SemVer](https://semver.org/lang/it/): `MAJOR.MINOR.PATCH`.

1. Aggiorna il file **`VERSION`** (per esempio `1.1.0`).
2. Aggiungi una sezione **`## [1.1.0] - AAAA-MM-GG`** in cima a `CHANGELOG.md`:
   diventa le note della release, che gli amministratori leggono prima
   di aggiornare (il registro delle modifiche è scritto in francese).
3. Fai il commit, crea il tag e invia:

   ```bash
   git commit -am "Version 1.1.0"
   git tag -a v1.1.0 -m "GrimPanBloc 1.1.0"
   git push && git push origin v1.1.0
   ```

Il workflow GitHub Actions `.github/workflows/release.yml` fa il resto:

- verifica che il tag corrisponda al file `VERSION`;
- costruisce l'archivio `grimpanbloc-v1.1.0.zip` (codice, `vendor/` senza i
  pacchetti di sviluppo, pacchetti JavaScript) e la sua impronta `.sha256`, con
  `bin/construire-release.sh`;
- pubblica anche una copia con il vecchio nome dell'applicazione,
  `grimpepan-v1.1.0.zip`: è quella che cercano le installazioni precedenti
  alla 1.3.0 per aggiornarsi;
- pubblica la release.

I siti installati la propongono poi ai loro amministratori, entro
24 ore al massimo.

Per costruire l'archivio in locale (per provarlo, per esempio):
`bin/construire-release.sh build/`.

> **Contratto tra versioni**: la route `/admin/mise-a-jour/finaliser` e il suo
> parametro `jeton` devono esistere in tutte le versioni. È la
> versione **nuova** che riceve questa richiesta, subito dopo la sostituzione del codice.

## Installazione per lo sviluppo (macOS)

In sviluppo, l'applicazione gira con il server locale della Symfony CLI,
senza Apache. I file `.htaccess` servono solo in produzione.

### Requisiti

- PHP ≥ 8.4 con le estensioni `pdo_mysql`, `gd`, `exif` e `intl`
- Composer 2
- Symfony CLI (server locale)
- MariaDB 11.8 (vedi [più sotto](#database-mariadb-locale))

```bash
brew install php composer
brew install symfony-cli/tap/symfony-cli
symfony check:requirements
```

### Avvio

```bash
git clone https://github.com/johnburnout/GrimPanBloc.git
cd GrimPanBloc
composer install

# Database (MariaDB deve girare sulla porta 3307, vedi più sotto)
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n

# Server locale in background
symfony serve -d
symfony open:local
```

Poi crea un account, un settore, poi una via.

In sviluppo, `APP_SECRET` è fornito da `.env.dev`. Per sovrascrivere una
variabile sulla tua macchina (credenziali del database, per esempio), usa
`.env.local`, che non è versionato.

### HTTPS in locale (facoltativo)

```bash
symfony server:ca:install     # chiede la password di macOS
symfony server:stop && symfony serve -d
```

L'applicazione è allora servita su https://127.0.0.1:8000.

### Dimensione delle foto caricate

Il modulo accetta foto fino a **15 MB**, ma PHP limita per impostazione predefinita
i caricamenti a 2 MB. Il file `php.ini` nella radice del progetto alza questi limiti
(`upload_max_filesize = 16M`, `post_max_size = 20M`, `memory_limit = 256M`). La
Symfony CLI lo legge automaticamente: basta riavviare il server dopo
averlo modificato (`symfony server:stop && symfony serve -d`).

Questo file serve solo in sviluppo. Sul server, vedi il
[passo 3](#3-impostazioni-php) dell'installazione Apache.

## Database MariaDB locale

La produzione gira su **MariaDB 11.8.6**. Lo sviluppo usa quindi
MariaDB 11.8, e non MySQL: le migrazioni Doctrine dipendono dalla piattaforma,
e Doctrine non sa generare una migrazione MariaDB a partire da un server MySQL.

Sulla macchina di sviluppo, MariaDB gira **accanto** a un MySQL esistente
(porta 3306):

| | Valore |
|---|---|
| Pacchetto | `mariadb@11.8` (Homebrew, keg-only) |
| Indirizzo | `127.0.0.1:3307`, utente `root` senza password |
| Dati | `/opt/homebrew/var/mariadb-11.8` |
| Avvio | LaunchAgent `~/Library/LaunchAgents/local.mariadb-11.8.plist` |
| Database | `grimpepan` |

> ⚠️ **Non usare `brew services start mariadb@11.8`**: questo servizio
> avvierebbe MariaDB su `/opt/homebrew/var/mysql`, la cartella dei dati di
> MySQL, e rischierebbe di danneggiarla.

Installazione da zero:

```bash
brew install mariadb@11.8
M=/opt/homebrew/opt/mariadb@11.8
mkdir -p /opt/homebrew/var/mariadb-11.8
$M/bin/mariadb-install-db --no-defaults --basedir=$M \
  --datadir=/opt/homebrew/var/mariadb-11.8 \
  --auth-root-authentication-method=normal --skip-test-db
```

Il LaunchAgent esegue `mariadbd --no-defaults --port=3307 --bind-address=127.0.0.1
--socket=/tmp/mariadb-3307.sock --datadir=/opt/homebrew/var/mariadb-11.8
--character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci`.

```bash
# avviare / fermare
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/local.mariadb-11.8.plist
launchctl bootout  gui/$(id -u)/local.mariadb-11.8

# console
/opt/homebrew/opt/mariadb@11.8/bin/mariadb -h127.0.0.1 -P3307 -uroot grimpepan
```

La connessione predefinita si trova in `.env`:

```
DATABASE_URL="mysql://root@127.0.0.1:3307/grimpepan?serverVersion=11.8.6-MariaDB&charset=utf8mb4"
```

**Con Docker**, `compose.yaml` fornisce anche un container `mariadb:11.8.6`.
Con `docker compose up -d`, la Symfony CLI rileva il container e fornisce
da sola il `DATABASE_URL` corretto.

## Comandi utili

```bash
# Server
symfony serve -d               # avviare in background
symfony server:log             # seguire i log
symfony server:stop            # fermare

# Database
php bin/console make:migration                 # dopo una modifica a un'entità
php bin/console doctrine:migrations:migrate -n # applicare le migrazioni
php bin/console doctrine:schema:validate       # verificare mapping e schema

# Foto
php bin/console app:photos:reduire --simulation  # elenca le foto oltre i 2000 px
php bin/console app:photos:reduire               # le riduce (file e dimensioni nel database)
php bin/console app:photos:miniatures            # crea le miniature mancanti (--toutes: le ricrea tutte)

# Verifiche
php bin/console lint:twig templates
php bin/console lint:container
php bin/console debug:router
php bin/console debug:translation it --only-missing   # testi non tradotti
```

**Creare un account amministratore** (la password viene chiesta senza mostrarla):

```bash
php bin/console app:utilisateur:creer admin@esempio.org Admin --admin
```

Per dare il ruolo a un account esistente: **Amministrazione → Utenti → Nomina admin**. La persona deve accedere di nuovo.

## Struttura del progetto

| Posizione | Contenuto |
|---|---|
| `src/Entity` | `User`, `Secteur`, `Photo`, `Voie`, `Prise`, `Croix` |
| `src/Enum` | `CotationFr`, `CotationCouleur`, `SystemeCotation`, `TypePrise` |
| `src/Controller` | Home, registrazione, accesso, settori, vie (creazione, modifica, chiusure, eliminazione) |
| `src/Form` | `RegistrationFormType`, `SecteurType`, `VoieType` (opzione `trace`: modulo completo o nome + descrizione) |
| `src/Security/VoieVoter.php` | Permessi `VOIE_MODIFIER` e `VOIE_MODIFIER_TRACE` |
| `src/Service/PhotoUploader.php` | Salvataggio delle foto caricate |
| `src/Service/PhotoOptimiseur.php` | Raddrizzamento EXIF e riduzione a 2000 × 2000 px |
| `src/Command/ReduirePhotosCommand.php` | Comando `app:photos:reduire` (foto già salvate) |
| `src/Service/MiniaturesPhotos.php` | Miniature da 800 px (`public/uploads/photos/miniatures/`), funzione Twig `miniature()` |
| `src/Command/MiniaturesPhotosCommand.php` | Comando `app:photos:miniatures` (miniature mancanti) |
| `templates/voie/_form.html.twig` | Modulo completo, comune a creazione e modifica |
| `templates/voie/_photo.html.twig` | Resa foto + prese (SVG) |
| `public/js/editeur.js` | Editor delle prese |
| `public/css/app.css` | Stili (palette allineata al logo) |
| `public/fonts/bricolage-grotesque/` | Font dei titoli e del logo, con la sua licenza |
| `assets/logo/` | Loghi SVG e favicon |
| `public/drapeaux/` | Bandiere del menu delle lingue (SVG) |
| `translations/` | Testi dell'interfaccia in cinque lingue (`messages+intl-icu`, `validators`, `security`) |
| `src/Locale/Langues.php`, `src/EventSubscriber/LangueListener.php` | Scelta della lingua dell'interfaccia (cookie, browser, account) |
| `public/setup/` | Procedura di installazione web (eliminata dopo l'installazione) |
| `src/Command/CreerUtilisateurCommand.php` | Comando `app:utilisateur:creer` (account amministratore…) |
| `src/MiseAJour/` | Verifica delle release GitHub, installazione di un aggiornamento, modalità manutenzione |
| `src/Cotation/ConsensusCotation.php` | Grado mostrato a partire dalle proposte dei validatori |
| `src/Controller/ProfilController.php` | Il mio account: profilo, le mie chiusure, le mie vie |
| `src/Controller/AdminReglagesController.php` | Impostazioni (club, SMTP, email di prova, invio di gruppo) e scheda da stampare |
| `src/Controller/AdminUtilisateursController.php` | Elenco degli utenti: amministratore, disattivazione, eliminazione |
| `src/Mail/` | Impostazioni SMTP (password cifrata con `APP_SECRET`) e invio delle email |
| `src/Site/IdentiteClub.php` | Nome e logo del club (`public/uploads/logo/`), tenuti in cache |
| `src/Site/ReglageHttps.php` | Impostazione «Usa sempre HTTPS», tenuta in cache (applicata da `ForcerHttpsSubscriber`) |
| `src/Security/UserChecker.php` | Rifiuto dell'accesso agli account disattivati |
| `tests/` | Test PHPUnit (`php bin/phpunit`) |
| `phpstan.dist.neon` | Analisi statica (`vendor/bin/phpstan analyse`), livello 6, nessun errore tollerato |
| `.php-cs-fixer.dist.php` | Stile di codice Symfony (`vendor/bin/php-cs-fixer fix`) |
| `src/Controller/AdminMiseAJourController.php` | Pagina «Aggiornamenti» (`/admin/mise-a-jour`) |
| `src/Command/MiseAJourCommand.php` | Comando `app:mise-a-jour` |
| `VERSION`, `CHANGELOG.md` | Versione installata e registro delle modifiche |
| `bin/construire-release.sh` | Costruzione dell'archivio di release |
| `.github/workflows/release.yml` | Pubblicazione automatica di una release a ogni tag `vX.Y.Z` |
| `migrations/` | Migrazioni Doctrine (generate su MariaDB) |
| `public/.htaccess` | Riscrittura degli URL per Apache senza VirtualHost (`symfony/apache-pack`) |
| `public/uploads/.htaccess` | Consente solo le immagini nella cartella delle foto |
| `php.ini` | Limiti PHP del server di sviluppo (foto fino a 16 MB) |
| `compose.yaml` | Container Docker facoltativi (MariaDB, Mailpit) |

## Scelte tecniche

- **Foto ridotte a 2000 × 2000 px** (JPEG qualità 85, progressivo; PNG e WebP
  mantengono il loro formato). Una foto già abbastanza piccola non viene salvata di nuovo,
  quindi non viene ricompressa. Poiché le prese sono in percentuale, una riduzione non
  le sposta.
- **Miniature da 800 px** in `uploads/photos/miniatures/`, con lo stesso nome della
  foto: gli elenchi caricano qualche decina di KB per via invece della
  foto intera. Una foto senza miniatura (più piccola di 800 px, o miniatura
  non ancora creata) viene mostrata intera: nulla si rompe se la creazione fallisce.
- **Prese in percentuale**: x, y e raggio sono salvati in % dell'immagine, per
  restare allineati qualunque sia la dimensione di visualizzazione.
- **Grado** salvato come testo (`6a`, `bleu`) con un **rango** numerico per
  ordinare e filtrare per difficoltà, qualunque sia la scala.
- **Nessuna build JavaScript**: l'editor è in JavaScript nativo, servito da `public/js/`.
- **CSRF**: protezione «stateless» di Symfony 8, che verifica l'intestazione
  `Origin` inviata dal browser.
- **Via convalidata**: `Voie::estValidee()` è vera appena una chiusura proviene da un
  climber diverso dall'autore.
- **Traduzioni**: chiavi in `translations/` (formato ICU, con le forme plurali
  di ogni lingua); i messaggi flash sono oggetti `TranslatableMessage` e gli
  errori dei servizi sono eccezioni traducibili (`src/Exception/`).

## Identità visiva

| File (`assets/logo/`) | Uso |
|---|---|
| `logo-inverse.svg` | Intestazione (sfondo scuro) |
| `mark-inverse.svg` | Intestazione su schermo piccolo (≤ 480 px) |
| `favicon.svg` | Icona della scheda |
| `logo.svg`, `mark.svg`, `mark-mono.svg` | Sfondi chiari, stampa |

- **Colori**: scuro `#1C1F24`, arancione `#E8622C`, chiaro `#F4F2EE`.
- **Font**: *Bricolage Grotesque* (variabile, pesi da 200 a 800), ospitato sul server,
  senza chiamate a Google Fonts. È usato per il logo e i titoli. Il testo
  corrente resta nel font di sistema.
- Il logo dell'intestazione è inserito come SVG *inline* (`{{ source('@logo/…') }}`):
  caricato come `<img>`, non potrebbe usare il font della pagina.

## Prossimi passi

- Più vie mostrate sulla stessa foto
- Pareri sul grado, commenti
- Amministrazione: gestione dei ruoli, moderazione, archiviazione delle vie smontate

## Licenza

Codice: GNU GPL v3 o successiva (`GPL-3.0-or-later`), vedi [`LICENSE`](LICENSE).
Font Bricolage Grotesque: SIL Open Font License 1.1, vedi
[`public/fonts/bricolage-grotesque/LICENSE.txt`](public/fonts/bricolage-grotesque/LICENSE.txt).
