<?php

/*
 * Assistant d'installation de GrimpePan : logique (vérifications, configuration,
 * commandes Symfony). Ce fichier ne fait rien s'il est appelé directement.
 */

\defined('GRIMPEPAN_SETUP') || exit;

final class Installateur
{
    public const PHP_MIN = '8.4.1';
    public const MARIADB_MIN = '11.8.0';
    public const EXTENSIONS = ['pdo_mysql', 'gd', 'exif', 'intl', 'ctype', 'iconv', 'mbstring', 'dom', 'xml', 'session', 'tokenizer'];

    /** Installation terminée : l'assistant est définitivement bloqué. */
    public readonly string $verrouFin;
    /** Installation en cours : seule la session qui l'a commencée peut continuer. */
    public readonly string $verrouSession;
    public readonly string $envLocal;

    public function __construct(public readonly string $racine)
    {
        $this->verrouFin = $racine.'/var/installation.lock';
        $this->verrouSession = $racine.'/var/setup-en-cours.lock';
        $this->envLocal = $racine.'/.env.local';
    }

    // --- Verrous ---------------------------------------------------------------

    /**
     * Raison pour laquelle l'assistant est fermé, ou null s'il peut être utilisé.
     */
    public function raisonBlocage(?string $jetonSession): ?string
    {
        if (is_file($this->verrouFin)) {
            return 'GrimpePan est déjà installé.';
        }
        $configure = is_file($this->envLocal) || is_file($this->racine.'/.env.local.php');
        if (!is_file($this->verrouSession)) {
            // Configuré sans l'assistant (installation manuelle, voir le README) : on n'y touche pas.
            return $configure ? 'GrimpePan est déjà configuré sur ce serveur (fichier .env.local présent).' : null;
        }
        $attendu = trim((string) file_get_contents($this->verrouSession));
        if (null === $jetonSession || !hash_equals($attendu, hash('sha256', $jetonSession))) {
            return 'Une installation est déjà en cours depuis un autre navigateur.';
        }

        return null;
    }

    /** Réserve l'installation pour la session courante (au premier enregistrement). */
    public function reserver(string $jetonSession): void
    {
        if (!is_file($this->verrouSession)) {
            $this->ecrire($this->verrouSession, hash('sha256', $jetonSession)."\n");
        }
    }

    public function terminer(): void
    {
        $this->ecrire($this->verrouFin, 'Installé avec l\'assistant le '.date('c')."\n");
        @unlink($this->verrouSession);
    }

    // --- Étape 1 : prérequis -------------------------------------------------------

    /**
     * @return list<array{libelle: string, ok: bool, detail: string, bloquant: bool}>
     */
    public function verifierPrerequis(): array
    {
        $r = [];

        // Fichiers dont le nom commence par un point : souvent oubliés par les clients FTP.
        ['restaures' => $restaures, 'manquants' => $manquants] = $this->restaurerFichiersCaches();
        $r[] = $this->verif('Fichier .env', !\in_array('.env', $manquants, true),
            'Absent : affichez les fichiers cachés dans votre client FTP et envoyez le fichier .env de l\'archive à la racine du projet.', true,
            \in_array('.env', $restaures, true) ? 'Absent après le transfert : restauré par l\'assistant.' : '');
        $htaccess = array_values(array_diff($manquants, ['.env']));
        $r[] = $this->verif('Fichiers .htaccess', [] === $htaccess,
            'Absents : '.implode(', ', $htaccess).'. Indispensables si Apache n\'a pas de VirtualHost dédié : envoyez-les depuis l\'archive.', false,
            ($h = array_values(array_diff($restaures, ['.env']))) ? 'Restaurés par l\'assistant : '.implode(', ', $h) : '');

        $r[] = $this->verif('PHP '.preg_replace('/\.0$/', '', self::PHP_MIN).' ou plus', version_compare(\PHP_VERSION, self::PHP_MIN, '>='), 'Version installée : '.\PHP_VERSION, true, 'Version installée : '.\PHP_VERSION);

        $manquantes = array_values(array_filter(self::EXTENSIONS, fn ($e) => !\extension_loaded($e)));
        $r[] = $this->verif('Extensions PHP', [] === $manquantes,
            'Manquantes : '.implode(', ', $manquantes), true, implode(', ', self::EXTENSIONS));

        $r[] = $this->verif('Dépendances (dossier vendor/)', is_file($this->racine.'/vendor/autoload_runtime.php'),
            'Absentes : lancez « composer install --no-dev --optimize-autoloader » ou utilisez une archive qui contient vendor/.', true);

        foreach (['var' => 'cache, journaux', 'public/uploads/photos' => 'photos envoyées'] as $dossier => $role) {
            $chemin = $this->racine.'/'.$dossier;
            $r[] = $this->verif("Écriture dans $dossier/ ($role)", is_dir($chemin) && is_writable($chemin),
                is_dir($chemin) ? 'Donnez le droit d\'écriture à l\'utilisateur du serveur web.' : 'Dossier absent.', true);
        }

        $r[] = $this->verif('Extension zip (mises à jour en un clic)', \extension_loaded('zip'),
            'Sans elle, les mises à jour se feront en SSH (php bin/console app:mise-a-jour).', false);
        $r[] = $this->verif('Écriture du fichier .env.local', is_writable($this->racine),
            'Sinon, l\'assistant affichera son contenu pour que vous le créiez vous-même.', false);
        $r[] = $this->verif('Écriture dans public/ (logos compilés)', is_writable($this->racine.'/public'),
            'Sinon, lancez « php bin/console asset-map:compile » après l\'installation.', false);

        $upload = $this->octets((string) ini_get('upload_max_filesize'));
        $post = $this->octets((string) ini_get('post_max_size'));
        $valeurs = sprintf('upload_max_filesize = %s, post_max_size = %s', ini_get('upload_max_filesize'), ini_get('post_max_size'));
        $r[] = $this->verif('Envoi de photos jusqu\'à 16 Mo', $upload >= 16 << 20 && $post >= 20 << 20,
            $valeurs.' (16M et 20M conseillés)', false, $valeurs);

        $memoire = $this->octets((string) ini_get('memory_limit'));
        $r[] = $this->verif('Mémoire PHP (redimensionnement des photos)', -1 === $memoire || $memoire >= 256 << 20,
            'memory_limit = '.ini_get('memory_limit').' (256M conseillé)', false, 'memory_limit = '.ini_get('memory_limit'));

        $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $r[] = $this->verif('Connexion HTTPS', $https,
            'Les mots de passe transitent en clair : activez HTTPS avant d\'ouvrir le site.', false);

        return $r;
    }

    /**
     * Recrée les fichiers cachés absents (.env, .htaccess) à partir des copies que
     * bin/construire-release.sh place dans _inc/fichiers-caches.php.
     *
     * @return array{restaures: list<string>, manquants: list<string>}
     */
    public function restaurerFichiersCaches(): array
    {
        $copies = is_file(__DIR__.'/fichiers-caches.php') ? (array) require __DIR__.'/fichiers-caches.php' : [];
        $restaures = $manquants = [];
        foreach (['.env', 'public/.htaccess', 'public/uploads/.htaccess'] as $rel) {
            $chemin = $this->racine.'/'.$rel;
            if (is_file($chemin)) {
                continue;
            }
            if (isset($copies[$rel]) && is_writable(\dirname($chemin)) && false !== @file_put_contents($chemin, $copies[$rel])) {
                $restaures[] = $rel;
            } else {
                $manquants[] = $rel;
            }
        }

        return ['restaures' => $restaures, 'manquants' => $manquants];
    }

    // --- Étape 2 : base de données --------------------------------------------------

    /**
     * Teste la connexion, crée la base si besoin et renvoie la version du serveur.
     *
     * @param array{hote: string, port: string, base: string, utilisateur: string, mot_de_passe: string} $p
     *
     * @return array{version: string, base_creee: bool, tables: int}
     */
    public function testerBase(array $p): array
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $p['base'])) {
            throw new \InvalidArgumentException('Nom de base invalide : lettres, chiffres et « _ » uniquement.');
        }
        if (!preg_match('/^[A-Za-z0-9.\-]{1,253}$/', $p['hote'])) {
            throw new \InvalidArgumentException('Nom d\'hôte invalide.');
        }
        if (!ctype_digit($p['port']) || (int) $p['port'] < 1 || (int) $p['port'] > 65535) {
            throw new \InvalidArgumentException('Port invalide.');
        }
        try {
            $pdo = new \PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $p['hote'], (int) $p['port']),
                $p['utilisateur'], $p['mot_de_passe'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5]);
        } catch (\PDOException $e) {
            throw new \RuntimeException('Connexion impossible : '.$e->getMessage());
        }

        $brute = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        if (!str_contains(strtolower($brute), 'mariadb')) {
            throw new \RuntimeException("Le serveur n'est pas MariaDB ($brute). GrimpePan est prévu pour MariaDB ".self::MARIADB_MIN.' ou plus.');
        }
        preg_match('/^\d+\.\d+\.\d+/', $brute, $m);
        $version = $m[0] ?? '0.0.0';
        if (version_compare($version, self::MARIADB_MIN, '<')) {
            throw new \RuntimeException("MariaDB $version est trop ancien : la version ".self::MARIADB_MIN.' ou plus est requise.');
        }

        $existe = (bool) $pdo->query('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '.$pdo->quote($p['base']))->fetchColumn();
        $creee = false;
        if (!$existe) {
            try {
                $pdo->exec("CREATE DATABASE `{$p['base']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $creee = true;
            } catch (\PDOException $e) {
                throw new \RuntimeException("La base « {$p['base']} » n'existe pas et l'utilisateur ne peut pas la créer. Créez-la depuis l'interface de votre hébergeur.");
            }
        }
        $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '.$pdo->quote($p['base']))->fetchColumn();

        return ['version' => $version, 'base_creee' => $creee, 'tables' => $tables];
    }

    /** Contenu du fichier .env.local de production. */
    public function contenuEnv(array $p, string $versionServeur): string
    {
        // Les caractères spéciaux sont encodés pour l'URL (« @ » → « %40 »)…
        $url = sprintf('mysql://%s:%s@%s:%d/%s?serverVersion=%s-MariaDB&charset=utf8mb4',
            rawurlencode($p['utilisateur']), rawurlencode($p['mot_de_passe']), $p['hote'], (int) $p['port'], $p['base'], $versionServeur);
        // … puis les « % » doublés : doctrine.yaml lit l'URL avec %env(resolve:…)%,
        // qui prendrait « %40…% » pour un paramètre Symfony.
        $url = str_replace('%', '%%', $url);

        return "# Configuration de production, écrite par l'assistant d'installation le ".date('d/m/Y à H:i')."\n"
            ."# Ne pas versionner ce fichier : il contient des secrets.\n"
            ."APP_ENV=prod\n"
            ."APP_DEBUG=0\n"
            .'APP_SECRET='.bin2hex(random_bytes(32))."\n"
            .'DATABASE_URL="'.addcslashes($url, '"\\$')."\"\n";
    }

    public function ecrireEnv(string $contenu): bool
    {
        if (!is_writable($this->racine)) {
            return false;
        }
        $this->ecrire($this->envLocal, $contenu);
        @chmod($this->envLocal, 0640);

        return true;
    }

    /** Le .env.local existe et désigne bien un environnement de production. */
    public function envPret(): bool
    {
        return is_file($this->envLocal) && preg_match('/^APP_ENV=prod\s*$/m', (string) file_get_contents($this->envLocal));
    }

    // --- Commandes Symfony (étapes 3 à 5) --------------------------------------

    /**
     * Lance une commande de la console Symfony dans ce processus PHP (pas besoin de exec()).
     *
     * @return array{code: int, sortie: string}
     */
    public function console(array $arguments): array
    {
        $this->restaurerFichiersCaches();
        if (!is_file($this->racine.'/.env')) {
            throw new \RuntimeException('Le fichier .env est absent de la racine du projet ('.$this->racine.'). '
                .'Affichez les fichiers cachés dans votre client FTP et envoyez le fichier .env de l\'archive.');
        }
        require_once $this->racine.'/vendor/autoload.php';
        (new \Symfony\Component\Dotenv\Dotenv())->bootEnv($this->racine.'/.env');

        $kernel = new \App\Kernel('prod', false);
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(true);
        $sortie = new \Symfony\Component\Console\Output\BufferedOutput();
        $code = $application->run(new \Symfony\Component\Console\Input\ArrayInput($arguments + ['--no-interaction' => true]), $sortie);
        $kernel->shutdown();

        return ['code' => $code, 'sortie' => trim($sortie->fetch())];
    }

    // --- Étape 5 : suppression de l'assistant -------------------------------------

    /** Supprime le dossier de l'assistant. Renvoie false s'il en reste quelque chose. */
    public function supprimerAssistant(string $dossier): bool
    {
        $elements = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dossier, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($elements as $e) {
            $e->isDir() ? @rmdir($e->getPathname()) : @unlink($e->getPathname());
        }
        @rmdir($dossier);

        return !file_exists($dossier);
    }

    // --- Utilitaires ---------------------------------------------------------------

    private function verif(string $libelle, bool $ok, string $detail, bool $bloquant, string $infoOk = ''): array
    {
        return ['libelle' => $libelle, 'ok' => $ok, 'detail' => $ok ? $infoOk : $detail, 'bloquant' => $bloquant];
    }

    private function octets(string $valeur): int
    {
        $valeur = trim($valeur);
        if ('-1' === $valeur) {
            return -1;
        }
        $n = (int) $valeur;

        return match (strtolower(substr($valeur, -1))) {
            'g' => $n << 30,
            'm' => $n << 20,
            'k' => $n << 10,
            default => $n,
        };
    }

    private function ecrire(string $chemin, string $contenu): void
    {
        if (false === @file_put_contents($chemin, $contenu, LOCK_EX)) {
            throw new \RuntimeException("Impossible d'écrire le fichier $chemin.");
        }
    }
}
