<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="assets/logo/logo-inverse.svg">
    <img src="assets/logo/logo.svg" alt="GrimPanBloc" width="330">
  </picture>
</p>

<p align="center">
  <a href="README.md">Français</a> · <a href="README.en.md">English</a> · <strong>Español</strong> · <a href="README.it.md">Italiano</a> · <a href="README.ru.md">Русский</a>
</p>

# GrimPanBloc

Creación y visualización de bloques y vías en una estructura artificial de
escalada de tipo «plafón».

Se fotografía una zona del muro, se marcan las presas de la vía en la foto, se
le da un grado, y los demás escaladores pueden consultarla y luego marcarla
como encadenada.

**Tecnología**: Symfony 8.1 · PHP ≥ 8.4.1 · Twig · Doctrine ORM · MariaDB 11.8 ·
JavaScript nativo (sin compilación) · AssetMapper.

> El [README](README.md) en francés es la versión de referencia; esta traducción
> puede ir ligeramente por detrás.

---

## Índice

- [Funcionalidades](#funcionalidades)
- [Reglas de funcionamiento](#reglas-de-funcionamiento)
- [Instalación con el asistente web](#instalación-con-el-asistente-web)
- [Instalación manual en un servidor Apache](#instalación-manual-en-un-servidor-apache)
- [Actualización del servidor](#actualización-del-servidor)
- [Publicar una nueva versión](#publicar-una-nueva-versión)
- [Instalación para desarrollo (macOS)](#instalación-para-desarrollo-macos)
- [Base de datos MariaDB local](#base-de-datos-mariadb-local)
- [Comandos útiles](#comandos-útiles)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Decisiones técnicas](#decisiones-técnicas)
- [Identidad visual](#identidad-visual)
- [Próximos pasos](#próximos-pasos)
- [Licencia](#licencia)

---

## Funcionalidades

- **Idiomas**: francés, inglés, español, italiano y ruso. Se usa por defecto el idioma del
  navegador; el menú del pie de página permite cambiarlo (la elección se recuerda y se
  guarda en la cuenta del escalador conectado). Los textos están en `translations/`
  (formato ICU para los plurales); el asistente de instalación y los comandos siguen en francés.
- **Cuentas**: registro (correo, nombre de usuario, contraseña), inicio de sesión con «recordarme», cierre de sesión.
- **Sectores**: zonas del muro (plafón, desplome, placa…), cada una con sus fotos y sus vías.
- **Creación de una vía**:
  - subida de una foto (JPEG, PNG o WebP) o reutilización de una foto del sector.
    La foto se endereza según sus datos EXIF y se reduce a **2000 × 2000 px como máximo**,
    conservando sus proporciones, para ahorrar espacio en disco. Una **miniatura**
    (800 px de lado como máximo) se usa en las listas de vías y en la administración;
  - marcado de las presas en la foto: salida, mano, pie, top;
  - grado francés (3a → 9c) o color (verde → blanco).
- **Editor de presas** (táctil y ratón):
  - tocar la foto coloca una presa;
  - tocar una presa cambia su tipo, arrastrarla la mueve;
  - la goma borra una presa, «Deshacer» vuelve atrás;
  - el control «Tamaño» ajusta las siguientes presas **y la última colocada o movida**.
- **Visualización de una vía**: foto oscurecida, presas rodeadas del color de su tipo.
- **Lista de vías** filtrable por sector, escala y rango de dificultad.
- **Marcas**: «He encadenado esta vía», con el grado que se propone; luego se puede cambiar o quitar la marca.
- **Grado por consenso**: el grado mostrado es el que reúne a más escaladores que han encadenado la vía (ver las reglas más abajo).
- **Modificación** de una vía por su creador, según las reglas de más abajo.
- **Eliminación** por el creador o un administrador, con una salvaguarda para las vías ya encadenadas.
- **Mi cuenta** (clic en el nombre de usuario): cambiar el correo y la contraseña (el nombre de usuario no se puede cambiar), lista de sus marcas y de sus vías.
- **Administración** (solo administradores):
  - **Ajustes**: seguridad («Usar siempre HTTPS»), nombre y logo del rocódromo o del club (mostrados en la cabecera y en la hoja para imprimir), servidor SMTP, correo de prueba, envío de un mensaje a todos los usuarios o solo a los administradores;
  - **Usuarios**: nombrar o quitar un administrador, desactivar o reactivar una cuenta, eliminar una cuenta;
  - **Fotos**: fotos que ninguna vía usa (que quedaron en su sector tras eliminar una vía o cambiar su foto), con su sector, y eliminación definitiva;
  - **Hoja para imprimir**: cartel A4 con el logo del club y un código QR hacia el sitio;
  - **Actualizaciones**: aviso cuando se publica una nueva versión, y actualización con un clic.

## Reglas de funcionamiento

| Situación | Creador | Otro escalador | Administrador |
|---|---|---|---|
| Vía que **nadie más** ha encadenado | modifica **todo**: foto, presas, grado, nombre, descripción | — | — |
| Vía **encadenada** por al menos otro escalador | modifica **solo el nombre y la descripción** | — | — |
| Eliminar una vía no encadenada | confirmación simple | — | confirmación simple |
| Eliminar una vía encadenada | debe escribir **`ELIMINAR`** (en el idioma de la interfaz) | — | debe escribir **`ELIMINAR`** |
| Poner / quitar su marca | sí | sí | sí |

Precisiones:

- La marca del **creador** en su propia vía no la bloquea: solo la de otro escalador lo hace.
- Si se quitan todas las marcas de los demás escaladores, la vía vuelve a ser totalmente modificable.
- Eliminar una vía elimina también sus presas y sus marcas.
- Las reglas de modificación están en `src/Security/VoieVoter.php`. El servidor comprueba todas estas reglas, no solo la interfaz.

### Grado

- El **creador** propone un grado al crear la vía. Se muestra mientras nadie más la haya encadenado.
- Cada escalador que **valida** la vía (pone su marca) propone su grado, que puede cambiar después en la ficha de la vía.
- En cuanto hay al menos una propuesta de un validador, solo cuentan estas propuestas: el grado mostrado es **el más propuesto**.
- **Empate**: la propuesta del creador desempata si está entre los grados empatados; si no, se toma la **mediana** de los grados empatados (con un número par, el más fácil de los dos centrales).
- La marca del creador no vota. La lista, la ordenación y los filtros usan el grado mostrado.
- La regla está en `src/Cotation/ConsensusCotation.php` (probada por `tests/Cotation/ConsensusCotationTest.php`).

### Cuentas

- Una cuenta **desactivada** ya no puede iniciar sesión; es reversible. Su sesión en curso se cierra de inmediato.
- Una cuenta **eliminada** pierde su correo y su contraseña (irreversible). Su nombre de usuario queda reservado y visible en sus vías y marcas, que siguen visibles y siguen contando; solo un administrador puede eliminar sus vías.
- Siempre queda al menos **un administrador activo**: el último no puede ser degradado, desactivado ni eliminado.
- Cuando cambia el rol de una cuenta, su sesión se cierra: debe volver a iniciar sesión (los permisos se leen al iniciar sesión).

### Datos personales (RGPD)

Para retomar en la página de privacidad del club, si existe:

- **Datos conservados** de cada cuenta: correo, nombre de usuario, contraseña (cifrada con hash,
  nunca en claro), fecha de registro, estado (administrador, activo), idioma de la interfaz.
  La aplicación no guarda ni direcciones IP ni historial de conexiones; el servidor web
  lleva sus propios registros de acceso.
- **Contenidos publicados**: vías (foto, presas, nombre, descripción), marcas y
  grados propuestos, mostrados con el nombre de usuario.
- **Cookies**: sesión de conexión, cookie «Mantener la sesión iniciada» (30 días)
  solo si se marca la casilla, e idioma elegido para la interfaz. Ninguna cookie de
  medición de audiencia ni de servicios de terceros.
- **Eliminación de una cuenta**: la hace un administrador, a petición de la
  persona (no hay botón de eliminación en «Mi cuenta»). El correo
  y la contraseña se borran. El **nombre de usuario sigue visible** en las vías y
  las marcas, que siguen visibles y siguen contando para el grado: son
  contribuciones a una guía común. Si la persona pide también su
  retirada, un administrador elimina sus vías una a una.

## Instalación con el asistente web

Es la forma más sencilla de instalar GrimPanBloc, y también sirve para
alojamientos compartidos sin acceso SSH. El asistente se encuentra en
`public/setup/` y se abre en un navegador. Está en francés.

### Obtener el archivo

Descarga **`grimpanbloc-vX.Y.Z.zip`** desde la
[página de publicaciones](https://github.com/johnburnout/GrimPanBloc/releases/latest).
Contiene todo lo necesario, incluido `vendor/`: el asistente no ejecuta
Composer. La suma de control del archivo se proporciona al lado (`.sha256`).

Sube el contenido de la carpeta `grimpanbloc/` al servidor y haz que la
raíz web apunte a la carpeta **`public/`**.

> **Archivos ocultos**: `.env`, `public/.htaccess` y `public/uploads/.htaccess`
> empiezan por un punto, y muchos clientes FTP los ignoran por defecto
> (FileZilla: *Servidor → Forzar mostrar archivos ocultos*). El asistente
> los vuelve a crear si faltan; aun así, comprueba que estén en el servidor.

Los requisitos son los mismos que para la
instalación manual: PHP 8.4.1 o superior, MariaDB 11.8, Apache con
`mod_rewrite`, y la extensión PHP `zip` para las actualizaciones con un clic.

> **Ajustes de PHP en un alojamiento compartido**: sin acceso a `php.ini`, ajusta
> estos límites en el panel del proveedor (a menudo *Configuración PHP*), sin
> dejar «Por defecto», cuyo valor se desconoce (a menudo 128M de memoria):
>
> | Ajuste | Valor | Por qué |
> |---|---|---|
> | Memoria máxima (`memory_limit`) | **256M** | GD decodifica las fotos enteras para enderezarlas y reducirlas; 256M cubren el límite de 25 megapíxeles |
> | Tamaño máximo de subida (`upload_max_filesize`) | 16M o más | fotos de hasta 15 MB |
> | Tamaño máximo de HTTP POST (`post_max_size`) | 20M o más | la foto más el resto del formulario |
>
> El asistente avisa si la memoria es insuficiente, pero se elimina tras la
> instalación: vuelve a comprobarlo si el proveedor cambia sus ajustes.

Para construir tú mismo el archivo a partir del código: `bin/construire-release.sh`
(ver [Publicar una nueva versión](#publicar-una-nueva-versión)).

### Lanzar el asistente

Abre `https://tu-dominio/setup/`. Mientras GrimPanBloc no esté instalado, la
página de inicio del sitio redirige allí por sí sola.

| Paso | Qué hace el asistente |
|---|---|
| 1. Requisitos | Comprueba PHP, las extensiones, los permisos de escritura y los límites de subida. Los puntos rojos bloquean, los naranjas son consejos. |
| 2. Base de datos | Prueba la conexión a MariaDB, crea la base si hace falta y escribe `.env.local` (`APP_ENV=prod`, `APP_SECRET` aleatorio, `DATABASE_URL`). Si PHP no puede crear este archivo, muestra su contenido para que lo crees tú. |
| 3. Tablas | Ejecuta las migraciones de Doctrine. |
| 4. Administrador | Crea la primera cuenta, con el rol de administrador. |
| 5. Finalización | Compila los logos, prepara la caché, **se bloquea y luego elimina la carpeta `public/setup/`**. |

### Seguridad del asistente

- **Lanza la instalación justo después de subir los archivos.** Antes del paso 2,
  cualquier visitante podría abrir el asistente.
- Desde el paso 2, la instalación queda **reservada al navegador que la empezó**
  (archivo `var/setup-en-cours.lock`). Otro navegador recibe un error 403.
- Una vez terminada la instalación, `var/installation.lock` bloquea el asistente
  **definitivamente**, aunque la carpeta `public/setup/` vuelva (tras un
  `git pull`, por ejemplo).
- En un servidor instalado a mano (con un `.env.local` ya presente),
  el asistente también se niega a funcionar.
- Si el servidor web no puede eliminar `public/setup/`, el asistente
  lo indica: **elimina la carpeta tú mismo**.

**Para volver a empezar una instalación** (tras un error o un navegador cerrado),
elimina en el servidor `.env.local`, `var/setup-en-cours.lock` y
`var/installation.lock`, vuelve a poner la carpeta `public/setup/` si hace falta y
abre de nuevo `/setup/`.

## Instalación manual en un servidor Apache

Para un servidor en el que tienes acceso SSH y control sobre Apache (VPS, servidor dedicado).
Este método no usa el asistente: elimina la carpeta `public/setup/` después de
la instalación.

Los comandos siguientes están pensados para **Debian 13 (Trixie)**, que proporciona directamente
PHP 8.4 y MariaDB 11.8. En Ubuntu o en una Debian más antigua, hay que añadir
el repositorio PHP de Ondřej Surý (PHP 8.4) y el repositorio oficial de MariaDB (11.8);
el resto es idéntico.

Convenciones utilizadas (adáptalas):

| | Valor |
|---|---|
| Dominio | `grimpanbloc.example.org` |
| Carpeta de la aplicación | `/var/www/grimpanbloc` |
| Usuario que despliega | `deploy` |
| Usuario de Apache y PHP-FPM | `www-data` |

> **Alojamiento compartido** (sin acceso a la configuración de Apache): ver
> [la variante al final de esta sección](#variante-alojamiento-compartido).

### 1. Paquetes

```bash
sudo apt update
sudo apt install apache2 mariadb-server git unzip composer \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-gd php8.4-intl \
  php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip
```

La extensión `exif`, necesaria para enderezar las fotos, está incluida en
`php8.4-common` en Debian. Para comprobarlo:

```bash
php -m | grep -E "pdo_mysql|gd|exif|intl"
```

### 2. Apache y PHP-FPM

Apache pasa los archivos `.php` a PHP-FPM, con el MPM *event*. No hay
`mod_php`.

```bash
sudo a2dismod php8.4 mpm_prefork 2>/dev/null   # si mod_php estaba instalado
sudo a2enmod mpm_event proxy_fcgi setenvif rewrite headers
sudo a2enconf php8.4-fpm
sudo systemctl restart php8.4-fpm apache2
```

### 3. Ajustes de PHP

El formulario acepta fotos de hasta 15 MB, pero PHP las limita por defecto a
2 MB. Crea `/etc/php/8.4/fpm/conf.d/90-grimpanbloc.ini`:

```ini
upload_max_filesize = 16M
post_max_size = 20M
memory_limit = 256M        ; enderezado de fotos grandes (GD), hasta 25 Mpx

; Rendimiento en producción
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0   ; vaciar la caché al desplegar (ver «Actualización»)
realpath_cache_size = 4096K
realpath_cache_ttl = 600
```

```bash
sudo systemctl reload php8.4-fpm
```

### 4. Base de datos

```bash
sudo mariadb
```

```sql
CREATE DATABASE grimpanbloc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'grimpanbloc'@'localhost' IDENTIFIED BY 'contraseña-robusta';
GRANT ALL PRIVILEGES ON grimpanbloc.* TO 'grimpanbloc'@'localhost';
FLUSH PRIVILEGES;
```

Si la contraseña contiene caracteres especiales (`@`, `:`, `/`, `#`…), hay
que codificarla en la URL de conexión (por ejemplo `@` se convierte en `%40`).

### 5. Código de la aplicación

```bash
sudo mkdir -p /var/www/grimpanbloc
sudo chown deploy:www-data /var/www/grimpanbloc
git clone https://github.com/johnburnout/GrimPanBloc.git /var/www/grimpanbloc
cd /var/www/grimpanbloc
```

Crea `/var/www/grimpanbloc/.env.local`. Este archivo **no está versionado** y
contiene los secretos:

```bash
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<resultado de: openssl rand -hex 32>
DATABASE_URL="mysql://grimpanbloc:contraseña-robusta@127.0.0.1:3306/grimpanbloc?serverVersion=11.8.6-MariaDB&charset=utf8mb4"
```

```bash
chmod 640 .env.local
```

Después instala e inicializa la aplicación:

```bash
composer install --no-dev --optimize-autoloader
composer dump-env prod                          # compila .env* en .env.local.php (más rápido)
php bin/console doctrine:migrations:migrate -n
php bin/console asset-map:compile               # logos y favicon (carpeta assets/)
php bin/console cache:clear
```

### 6. Permisos de los archivos

PHP-FPM (`www-data`) debe poder escribir en `var/` (caché, registros) y en la
carpeta de fotos. No necesita escribir en ningún otro sitio.

```bash
cd /var/www/grimpanbloc
sudo chown -R deploy:www-data .
sudo find . -type d -exec chmod 750 {} +
sudo find . -type f -exec chmod 640 {} +
sudo chmod 750 bin/console
sudo chmod -R g+w var public/uploads/photos
sudo find var public/uploads/photos -type d -exec chmod g+s {} +   # archivos nuevos en el grupo www-data
```

> La carpeta `public/uploads/photos/` contiene las fotos subidas. **No
> están versionadas**: esta carpeta debe **conservarse** en cada
> despliegue y **copiarse** junto con la base de datos.

### 7. VirtualHost

Crea `/etc/apache2/sites-available/grimpanbloc.conf`:

```apache
<VirtualHost *:80>
    ServerName grimpanbloc.example.org
    DocumentRoot /var/www/grimpanbloc/public

    <Directory /var/www/grimpanbloc/public>
        DirectoryIndex index.php
        # Configuración completa aquí: los .htaccess no se leen (más rápido)
        AllowOverride None
        Require all granted
        FallbackResource /index.php
    </Directory>

    # Carpeta de fotos: solo imágenes, ningún script
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

    # Caché del navegador de larga duración para los archivos versionados por AssetMapper
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

Certbot crea el VirtualHost `*:443`, añade la redirección HTTP → HTTPS y
programa la renovación automática del certificado.

### HTTPS obligatorio

Una vez instalado el certificado (Certbot, o HTTPS automático del proveedor), abre
**Administración → Ajustes** en `https://` y marca **«Usar siempre HTTPS»**:
los visitantes que llegan por `http://` se redirigen, y los navegadores recuerdan la
elección durante 30 días (cabecera HSTS). El ajuste solo se activa desde una página en
HTTPS: si PHP no detectara el HTTPS (proxy mal configurado), la redirección
entraría en bucle.

### 9. Comprobación

- https://grimpanbloc.example.org muestra la lista de vías;
- crea una cuenta, un sector y luego una vía con una foto de varios MB;
- en caso de error: `/var/log/apache2/grimpanbloc_error.log` y
  `/var/www/grimpanbloc/var/log/prod.log`.

### Variante: alojamiento compartido

Sin acceso a la configuración de Apache, el proyecto incluye dos archivos
`.htaccess` que cumplen la misma función que el VirtualHost:

| Archivo | Función |
|---|---|
| `public/.htaccess` | Envía todas las peticiones a `index.php` (paquete `symfony/apache-pack`) |
| `public/uploads/.htaccess` | Solo permite imágenes en la carpeta de fotos |

El proveedor debe ofrecer **`mod_rewrite`** y **`AllowOverride All`**, y
la **raíz web debe apuntar a la carpeta `public/`**. Si no es posible,
**no** hay que publicar todo el proyecto: `.env.local`, `var/` y `vendor/` nunca
deben ser accesibles desde la web.

Los comandos del paso 5 se ejecutan entonces por SSH, o en local antes de subir
los archivos. Los límites de subida del paso 3 se ajustan en el panel del
proveedor o en un `.user.ini`.

### Otro servidor web: nginx

GrimPanBloc solo se prueba con **Apache**. Con nginx, los archivos
`.htaccess` se **ignoran**: sus dos funciones deben reproducirse en la
configuración del sitio.

1. **Enviar las peticiones a `public/index.php`**, como en cualquier aplicación
   Symfony (ver la [documentación de Symfony sobre nginx](https://symfony.com/doc/current/setup/web_server_configuration.html#nginx)).
2. **Proteger `public/uploads/`**: servir solo imágenes y no
   ejecutar nunca PHP allí. Sin esta regla, la seguridad de las fotos depende solo de la
   aplicación (nombre de archivo aleatorio, extensión tomada del tipo real de
   la imagen), lo cual funciona, pero sin una segunda barrera.

Ejemplo para adaptar, **no probado por el proyecto**:

```nginx
root /var/www/grimpanbloc/public;
client_max_body_size 20M;

location / {
    try_files $uri /index.php$is_args$args;
}

# Fotos y logo: solo imágenes, nunca PHP
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

# No se ejecuta ningún otro archivo PHP (asistente de instalación incluido)
location ~ \.php$ {
    return 404;
}
```

El asistente de instalación web (`public/setup/`) está pensado para Apache: con
nginx, sigue la instalación manual.

## Actualización del servidor

GrimPanBloc comprueba **una vez al día** si se ha publicado una nueva versión
en GitHub. Si es así, los administradores ven un aviso en la parte superior de las
páginas, y el detalle está en la página **Actualizaciones** (`/admin/mise-a-jour`).

> **Antes de cualquier actualización, haz una copia de la base de datos** (herramienta del proveedor, o
> `mariadb-dump -u grimpanbloc -p grimpanbloc > grimpanbloc-$(date +%F).sql`).
> El código se copia automáticamente.

### Con un clic, desde la administración

En la página **Actualizaciones**, el botón «Actualizar»:

1. descarga el archivo de la versión y comprueba su **suma SHA-256**;
2. controla su contenido y luego pone el sitio **en mantenimiento** (página 503 para los visitantes);
3. sustituye el código. El antiguo se mueve a `var/mise-a-jour/precedente-X.Y.Z-…/`;
4. con el código nuevo: vacía la caché, ejecuta las **migraciones** de la base,
   vuelve a compilar los logos y **quita el mantenimiento**.

**Nunca se tocan**: `.env.local`, `var/` y las fotos
(`public/uploads/photos/`). El asistente `public/setup/` no se reinstala.

Este método supone que el servidor web (PHP) puede escribir en la raíz del
proyecto, en `public/` y en `var/`: suele ser el caso en un
alojamiento compartido. Si no, la página lo indica y propone el comando SSH.

Si la API de GitHub rechaza la comprobación (límite de 60 peticiones por hora y por dirección,
que se alcanza a menudo en alojamientos compartidos), GrimPanBloc lee la última
versión directamente en github.com.

### Confianza en las publicaciones de GitHub

Las actualizaciones, con un clic o por SSH, instalan lo que se publica en las
[publicaciones del repositorio de GitHub](https://github.com/johnburnout/GrimPanBloc/releases).
La suma SHA-256 se publica **en la misma publicación** que el archivo: garantiza
que el archivo no se ha dañado por el camino, **no** que venga realmente
de los mantenedores. Dicho de otro modo, cualquiera que pueda publicar una versión en este repositorio
puede hacer que su código se instale en todos los sitios que se actualicen.

En la práctica:

- por parte de los mantenedores: cuenta de GitHub protegida con autenticación en dos pasos,
  acceso de escritura al repositorio reservado a unas pocas personas de confianza;
- por parte del sitio: la actualización **nunca es automática**, un administrador debe
  hacer clic. En caso de duda sobre una versión (versión inesperada, notas de versión
  extrañas), no la instales e infórmate.

En el futuro se podría añadir una verificación más fuerte (firma de los archivos,
atestaciones de GitHub).

### Por SSH

```bash
cd /var/www/grimpanbloc
php bin/console app:mise-a-jour --verifier   # ¿hay una versión nueva?
php bin/console app:mise-a-jour              # instalarla (mismo mecanismo que con un clic)
sudo systemctl reload php8.4-fpm             # vacía OPcache (validate_timestamps = 0)
```

Ejecuta el comando con el usuario propietario de los archivos (`deploy` en la
instalación manual).

### Volver a la versión anterior

La versión antigua se queda en `var/mise-a-jour/precedente-X.Y.Z-…/`, que
contiene todos los archivos sustituidos. Para volver atrás, vuelve a poner esos
elementos en la raíz, restaura la copia de la base si se aplicó una migración
y vacía la caché (`rm -rf var/cache/prod`).

### Si se interrumpe una actualización

El modo mantenimiento (`var/maintenance.lock`) se quita solo al cabo de
**30 minutos**. Para quitarlo enseguida, elimina este archivo.

### Instalación hecha con git (sin el archivo)

```bash
cd /var/www/grimpanbloc
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate -n
php bin/console asset-map:compile
php bin/console cache:clear
sudo systemctl reload php8.4-fpm
```

### Notas sobre algunas versiones

- **Reducción de fotos**: tras la actualización que la introduce, ejecuta una vez
  `php bin/console app:photos:reduire` para reducir las fotos ya subidas.
  Empieza con `--simulation` para ver lo que se va a hacer.
- **Idiomas** (2.0.0): la actualización añade una columna a la cuenta de los usuarios
  (idioma elegido). Los mensajes que se muestran durante la actualización desde una 1.x
  siguen en francés.
- **Miniaturas de las fotos** (1.3.11): la actualización crea las de las fotos ya
  subidas, una vez reabierto el sitio. Si lo indica, termina por SSH con
  `php bin/console app:photos:miniatures`. Mientras tanto, las listas muestran
  las fotos completas.

## Publicar una nueva versión

Las versiones siguen [SemVer](https://semver.org/lang/es/): `MAYOR.MENOR.PARCHE`.

1. Actualiza el archivo **`VERSION`** (por ejemplo `1.1.0`).
2. Añade una sección **`## [1.1.0] - AAAA-MM-DD`** al principio de `CHANGELOG.md`:
   se convierte en las notas de la versión, que los administradores leen antes
   de actualizar (el registro de cambios se escribe en francés).
3. Haz el commit, crea la etiqueta y envía:

   ```bash
   git commit -am "Version 1.1.0"
   git tag -a v1.1.0 -m "GrimPanBloc 1.1.0"
   git push && git push origin v1.1.0
   ```

El flujo de trabajo de GitHub Actions `.github/workflows/release.yml` hace el resto:

- comprueba que la etiqueta corresponde al archivo `VERSION`;
- construye el archivo `grimpanbloc-v1.1.0.zip` (código, `vendor/` sin los
  paquetes de desarrollo, paquetes JavaScript) y su suma `.sha256`, con
  `bin/construire-release.sh`;
- publica también una copia con el antiguo nombre de la aplicación,
  `grimpepan-v1.1.0.zip`: es la que buscan las instalaciones anteriores
  a la 1.3.0 para actualizarse;
- publica la versión.

Los sitios instalados la proponen después a sus administradores, en un plazo
de 24 h como máximo.

Para construir el archivo en local (para probarlo, por ejemplo):
`bin/construire-release.sh build/`.

> **Contrato entre versiones**: la ruta `/admin/mise-a-jour/finaliser` y su
> parámetro `jeton` deben existir en todas las versiones. Es la
> versión **nueva** la que recibe esta petición, justo después de sustituir el código.

## Instalación para desarrollo (macOS)

En desarrollo, la aplicación funciona con el servidor local de la Symfony CLI,
sin Apache. Los archivos `.htaccess` solo sirven en producción.

### Requisitos

- PHP ≥ 8.4 con las extensiones `pdo_mysql`, `gd`, `exif` e `intl`
- Composer 2
- Symfony CLI (servidor local)
- MariaDB 11.8 (ver [más abajo](#base-de-datos-mariadb-local))

```bash
brew install php composer
brew install symfony-cli/tap/symfony-cli
symfony check:requirements
```

### Puesta en marcha

```bash
git clone https://github.com/johnburnout/GrimPanBloc.git
cd GrimPanBloc
composer install

# Base de datos (MariaDB debe funcionar en el puerto 3307, ver más abajo)
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n

# Servidor local en segundo plano
symfony serve -d
symfony open:local
```

Después, crea una cuenta, un sector y luego una vía.

En desarrollo, `APP_SECRET` lo proporciona `.env.dev`. Para sobrescribir una
variable en tu máquina (credenciales de la base, por ejemplo), usa
`.env.local`, que no está versionado.

### HTTPS en local (opcional)

```bash
symfony server:ca:install     # pide la contraseña de macOS
symfony server:stop && symfony serve -d
```

La aplicación se sirve entonces en https://127.0.0.1:8000.

### Tamaño de las fotos subidas

El formulario acepta fotos de hasta **15 MB**, pero PHP limita por defecto
las subidas a 2 MB. El archivo `php.ini` de la raíz del proyecto eleva estos límites
(`upload_max_filesize = 16M`, `post_max_size = 20M`, `memory_limit = 256M`). La
Symfony CLI lo lee automáticamente: basta con reiniciar el servidor después de
modificarlo (`symfony server:stop && symfony serve -d`).

Este archivo solo sirve en desarrollo. En el servidor, ver
el [paso 3](#3-ajustes-de-php) de la instalación con Apache.

## Base de datos MariaDB local

La producción funciona con **MariaDB 11.8.6**. Por eso el desarrollo usa
MariaDB 11.8, y no MySQL: las migraciones de Doctrine dependen de la plataforma,
y Doctrine no sabe generar una migración MariaDB a partir de un servidor MySQL.

En la máquina de desarrollo, MariaDB funciona **junto a** un MySQL existente
(puerto 3306):

| | Valor |
|---|---|
| Paquete | `mariadb@11.8` (Homebrew, keg-only) |
| Dirección | `127.0.0.1:3307`, usuario `root` sin contraseña |
| Datos | `/opt/homebrew/var/mariadb-11.8` |
| Arranque | LaunchAgent `~/Library/LaunchAgents/local.mariadb-11.8.plist` |
| Base | `grimpepan` |

> ⚠️ **No uses `brew services start mariadb@11.8`**: este servicio
> arrancaría MariaDB sobre `/opt/homebrew/var/mysql`, la carpeta de datos de
> MySQL, y podría dañarla.

Instalación desde cero:

```bash
brew install mariadb@11.8
M=/opt/homebrew/opt/mariadb@11.8
mkdir -p /opt/homebrew/var/mariadb-11.8
$M/bin/mariadb-install-db --no-defaults --basedir=$M \
  --datadir=/opt/homebrew/var/mariadb-11.8 \
  --auth-root-authentication-method=normal --skip-test-db
```

El LaunchAgent ejecuta `mariadbd --no-defaults --port=3307 --bind-address=127.0.0.1
--socket=/tmp/mariadb-3307.sock --datadir=/opt/homebrew/var/mariadb-11.8
--character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci`.

```bash
# arrancar / detener
launchctl bootstrap gui/$(id -u) ~/Library/LaunchAgents/local.mariadb-11.8.plist
launchctl bootout  gui/$(id -u)/local.mariadb-11.8

# consola
/opt/homebrew/opt/mariadb@11.8/bin/mariadb -h127.0.0.1 -P3307 -uroot grimpepan
```

La conexión por defecto está en `.env`:

```
DATABASE_URL="mysql://root@127.0.0.1:3307/grimpepan?serverVersion=11.8.6-MariaDB&charset=utf8mb4"
```

**Con Docker**, `compose.yaml` también proporciona un contenedor `mariadb:11.8.6`.
Con `docker compose up -d`, la Symfony CLI detecta el contenedor y proporciona
ella misma el `DATABASE_URL` correcto.

## Comandos útiles

```bash
# Servidor
symfony serve -d               # arrancar en segundo plano
symfony server:log             # seguir los registros
symfony server:stop            # detener

# Base de datos
php bin/console make:migration                 # tras modificar una entidad
php bin/console doctrine:migrations:migrate -n # aplicar las migraciones
php bin/console doctrine:schema:validate       # comprobar mapeo y esquema

# Fotos
php bin/console app:photos:reduire --simulation  # lista las fotos de más de 2000 px
php bin/console app:photos:reduire               # las reduce (archivos y dimensiones en la base)
php bin/console app:photos:miniatures            # crea las miniaturas que faltan (--toutes: las vuelve a crear)

# Comprobaciones
php bin/console lint:twig templates
php bin/console lint:container
php bin/console debug:router
php bin/console debug:translation es --only-missing   # textos sin traducir
```

**Crear una cuenta de administrador** (la contraseña se pide sin mostrarla):

```bash
php bin/console app:utilisateur:creer admin@ejemplo.org Admin --admin
```

Para dar el rol a una cuenta existente: **Administración → Usuarios → Nombrar admin**. La persona debe volver a iniciar sesión.

## Estructura del proyecto

| Ubicación | Contenido |
|---|---|
| `src/Entity` | `User`, `Secteur`, `Photo`, `Voie`, `Prise`, `Croix` |
| `src/Enum` | `CotationFr`, `CotationCouleur`, `SystemeCotation`, `TypePrise` |
| `src/Controller` | Inicio, registro, inicio de sesión, sectores, vías (creación, modificación, marcas, eliminación) |
| `src/Form` | `RegistrationFormType`, `SecteurType`, `VoieType` (opción `trace`: formulario completo o nombre + descripción) |
| `src/Security/VoieVoter.php` | Permisos `VOIE_MODIFIER` y `VOIE_MODIFIER_TRACE` |
| `src/Service/PhotoUploader.php` | Guardado de las fotos subidas |
| `src/Service/PhotoOptimiseur.php` | Enderezado EXIF y reducción a 2000 × 2000 px |
| `src/Command/ReduirePhotosCommand.php` | Comando `app:photos:reduire` (fotos ya guardadas) |
| `src/Service/MiniaturesPhotos.php` | Miniaturas de 800 px (`public/uploads/photos/miniatures/`), función Twig `miniature()` |
| `src/Command/MiniaturesPhotosCommand.php` | Comando `app:photos:miniatures` (miniaturas que faltan) |
| `templates/voie/_form.html.twig` | Formulario completo, común a la creación y la modificación |
| `templates/voie/_photo.html.twig` | Representación de la foto + presas (SVG) |
| `public/js/editeur.js` | Editor de presas |
| `public/css/app.css` | Estilos (paleta alineada con el logo) |
| `public/fonts/bricolage-grotesque/` | Tipografía de los títulos y del logo, con su licencia |
| `assets/logo/` | Logos SVG y favicon |
| `public/drapeaux/` | Banderas del menú de idiomas (SVG) |
| `translations/` | Textos de la interfaz en cinco idiomas (`messages+intl-icu`, `validators`, `security`) |
| `src/Locale/Langues.php`, `src/EventSubscriber/LangueListener.php` | Elección del idioma de la interfaz (cookie, navegador, cuenta) |
| `public/setup/` | Asistente de instalación web (eliminado tras la instalación) |
| `src/Command/CreerUtilisateurCommand.php` | Comando `app:utilisateur:creer` (cuenta de administrador…) |
| `src/MiseAJour/` | Comprobación de las versiones de GitHub, instalación de una actualización, modo mantenimiento |
| `src/Cotation/ConsensusCotation.php` | Grado mostrado a partir de las propuestas de los validadores |
| `src/Controller/ProfilController.php` | Mi cuenta: perfil, mis marcas, mis vías |
| `src/Controller/AdminReglagesController.php` | Ajustes (club, SMTP, correo de prueba, envío masivo) y hoja para imprimir |
| `src/Controller/AdminUtilisateursController.php` | Lista de usuarios: administrador, desactivación, eliminación |
| `src/Mail/` | Ajustes SMTP (contraseña cifrada con `APP_SECRET`) y envío de correos |
| `src/Site/IdentiteClub.php` | Nombre y logo del club (`public/uploads/logo/`), en caché |
| `src/Site/ReglageHttps.php` | Ajuste «Usar siempre HTTPS», en caché (aplicado por `ForcerHttpsSubscriber`) |
| `src/Security/UserChecker.php` | Rechazo del inicio de sesión de las cuentas desactivadas |
| `tests/` | Pruebas PHPUnit (`php bin/phpunit`) |
| `phpstan.dist.neon` | Análisis estático (`vendor/bin/phpstan analyse`), nivel 6, sin errores tolerados |
| `.php-cs-fixer.dist.php` | Estilo de código Symfony (`vendor/bin/php-cs-fixer fix`) |
| `src/Controller/AdminMiseAJourController.php` | Página «Actualizaciones» (`/admin/mise-a-jour`) |
| `src/Command/MiseAJourCommand.php` | Comando `app:mise-a-jour` |
| `VERSION`, `CHANGELOG.md` | Versión instalada y registro de cambios |
| `bin/construire-release.sh` | Construcción del archivo de la versión |
| `.github/workflows/release.yml` | Publicación automática de una versión con cada etiqueta `vX.Y.Z` |
| `migrations/` | Migraciones de Doctrine (generadas sobre MariaDB) |
| `public/.htaccess` | Reescritura de URL para Apache sin VirtualHost (`symfony/apache-pack`) |
| `public/uploads/.htaccess` | Solo permite imágenes en la carpeta de fotos |
| `php.ini` | Límites de PHP del servidor de desarrollo (fotos de hasta 16 MB) |
| `compose.yaml` | Contenedores Docker opcionales (MariaDB, Mailpit) |

## Decisiones técnicas

- **Fotos reducidas a 2000 × 2000 px** (JPEG calidad 85, progresivo; PNG y WebP
  conservan su formato). Una foto que ya es bastante pequeña no se vuelve a guardar,
  así que no se recomprime. Como las presas están en porcentaje, una reducción no
  las desplaza.
- **Miniaturas de 800 px** en `uploads/photos/miniatures/`, con el mismo nombre que
  la foto: las listas cargan unas decenas de KB por vía en lugar de la
  foto entera. Una foto sin miniatura (más pequeña que 800 px, o miniatura
  aún no creada) se muestra entera: nada se rompe si la creación falla.
- **Presas en porcentaje**: x, y y el radio se guardan en % de la imagen, para
  seguir alineados sea cual sea el tamaño de visualización.
- **Grado** guardado como texto (`6a`, `bleu`) con un **rango** numérico para
  ordenar y filtrar por dificultad, sea cual sea la escala.
- **Sin compilación de JavaScript**: el editor está en JavaScript nativo, servido desde `public/js/`.
- **CSRF**: protección «stateless» de Symfony 8, que comprueba la cabecera
  `Origin` enviada por el navegador.
- **Vía validada**: `Voie::estValidee()` es verdadera en cuanto una marca viene de un
  escalador distinto del autor.
- **Traducciones**: claves en `translations/` (formato ICU, con las formas de plural
  de cada idioma); los mensajes flash son objetos `TranslatableMessage` y los
  errores de los servicios son excepciones traducibles (`src/Exception/`).

## Identidad visual

| Archivo (`assets/logo/`) | Uso |
|---|---|
| `logo-inverse.svg` | Cabecera (fondo oscuro) |
| `mark-inverse.svg` | Cabecera en pantalla pequeña (≤ 480 px) |
| `favicon.svg` | Icono de pestaña |
| `logo.svg`, `mark.svg`, `mark-mono.svg` | Fondos claros, impresión |

- **Colores**: oscuro `#1C1F24`, naranja `#E8622C`, claro `#F4F2EE`.
- **Tipografía**: *Bricolage Grotesque* (variable, pesos de 200 a 800), alojada en el propio servidor,
  sin llamadas a Google Fonts. Se usa para el logo y los títulos. El texto
  corriente sigue en la tipografía del sistema.
- El logo de la cabecera se inserta como SVG *inline* (`{{ source('@logo/…') }}`):
  cargado como `<img>`, no podría usar la tipografía de la página.

## Próximos pasos

- Varias vías mostradas en una misma foto
- Opiniones sobre el grado, comentarios
- Administración: gestión de roles, moderación, archivo de las vías desmontadas

## Licencia

Código: GNU GPL v3 o posterior (`GPL-3.0-or-later`), ver [`LICENSE`](LICENSE).
Tipografía Bricolage Grotesque: SIL Open Font License 1.1, ver
[`public/fonts/bricolage-grotesque/LICENSE.txt`](public/fonts/bricolage-grotesque/LICENSE.txt).
