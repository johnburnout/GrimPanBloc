<?php

namespace App\MiseAJour;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Installe une release GitHub par-dessus la version en place.
 *
 *  1. télécharge l'archive et vérifie son empreinte SHA-256 ;
 *  2. l'extrait et contrôle son contenu (VERSION, vendor/) ;
 *  3. active le mode maintenance ;
 *  4. remplace le code : l'ancien est déplacé (et non supprimé) dans
 *     var/mise-a-jour/precedente-X.Y.Z/, ce qui sert de sauvegarde ;
 *  5. vide le cache compilé et l'OPcache.
 *
 * La suite (migrations, logos, fin de la maintenance) doit tourner avec le
 * NOUVEAU code : c'est finaliser(), appelé par une nouvelle requête ou un
 * nouveau processus.
 *
 * Jamais touchés : .env.local, .env.local.php, var/, public/uploads/ (photos).
 * Jamais réinstallé : public/setup/ (assistant d'installation).
 */
final class InstallateurMiseAJour
{
    private const TAILLE_MAX = 300 * 1024 * 1024;
    /** Éléments de la racine conservés tels quels. */
    private const CONSERVES_RACINE = ['var', '.env.local', '.env.local.php', 'public'];
    /** Éléments de public/ traités à part. */
    private const CONSERVES_PUBLIC = ['uploads', 'setup', 'assets'];

    private readonly Filesystem $fs;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly VersionInstallee $version,
        private readonly Maintenance $maintenance,
        private readonly string $racine,
    ) {
        $this->fs = new Filesystem();
    }

    public function dossierTravail(): string
    {
        return $this->racine.'/var/mise-a-jour';
    }

    /**
     * Problèmes qui empêchent la mise à jour depuis le navigateur.
     *
     * @return list<string>
     */
    public function problemes(): array
    {
        $p = [];
        if (!class_exists(\ZipArchive::class)) {
            $p[] = 'L\'extension PHP « zip » est absente.';
        }
        foreach (['' => 'la racine du projet', '/public' => 'public/', '/var' => 'var/'] as $chemin => $nom) {
            if (!is_writable($this->racine.$chemin)) {
                $p[] = "Le serveur web ne peut pas écrire dans $nom.";
            }
        }

        return $p;
    }

    /**
     * Étapes 1 à 5. Renvoie le jeton de maintenance à passer à finaliser().
     *
     * @param callable(string): void $journal reçoit une ligne par étape
     */
    public function installer(Release $release, callable $journal): string
    {
        if (!$release->estInstallable()) {
            throw new \RuntimeException("La release {$release->tag} ne contient pas d'archive installable.");
        }
        if (!$this->version->estDepasseePar($release->version)) {
            throw new \RuntimeException("La version {$release->version} n'est pas plus récente que la version installée ({$this->version->get()}).");
        }
        if ($p = $this->problemes()) {
            throw new \RuntimeException(implode("\n", $p));
        }

        $travail = $this->dossierTravail();
        $this->fs->remove([$travail.'/telechargement.zip', $travail.'/nouvelle']);
        $this->fs->mkdir($travail);

        // 1. Téléchargement et empreinte
        $zip = $travail.'/telechargement.zip';
        $this->telecharger($release->urlArchive, $zip);
        $attendue = strtolower(strtok(trim($this->httpClient->request('GET', $release->urlEmpreinte, ['timeout' => 20])->getContent()), " \t"));
        $obtenue = hash_file('sha256', $zip);
        if (!preg_match('/^[0-9a-f]{64}$/', $attendue) || !hash_equals($attendue, $obtenue)) {
            @unlink($zip);
            throw new \RuntimeException('L\'empreinte SHA-256 de l\'archive ne correspond pas : téléchargement corrompu ou modifié.');
        }
        $journal(sprintf('Archive téléchargée (%s Mo), empreinte SHA-256 vérifiée.', number_format(filesize($zip) / 1048576, 1, ',', ' ')));

        // 2. Extraction et contrôle
        $nouvelle = $this->extraire($zip, $travail.'/nouvelle');
        $versionArchive = trim((string) @file_get_contents($nouvelle.'/VERSION'));
        if ($versionArchive !== $release->version) {
            throw new \RuntimeException("L'archive annonce la version « $versionArchive » au lieu de {$release->version}.");
        }
        foreach (['vendor/autoload_runtime.php', 'bin/console', 'public/index.php', 'src/Kernel.php'] as $f) {
            if (!is_file($nouvelle.'/'.$f)) {
                throw new \RuntimeException("Archive incomplète : $f est absent.");
            }
        }
        $journal('Archive extraite et contrôlée.');

        // 3. Maintenance
        $jeton = $this->maintenance->activer();
        $journal('Site en maintenance.');

        // 4. Remplacement du code
        try {
            $precedente = $this->remplacer($nouvelle);
        } catch (\Throwable $e) {
            $this->maintenance->desactiver();
            throw $e;
        }
        $journal('Code remplacé. Ancienne version conservée dans '.substr($precedente, \strlen($this->racine) + 1).'/.');

        // 5. Caches
        $this->viderCaches();
        $journal('Cache vidé.');

        $this->fs->remove([$zip, $travail.'/nouvelle']);
        $this->nettoyerAnciennesSauvegardes($precedente);

        return $jeton;
    }

    /**
     * Dernière étape, avec le nouveau code : migrations, logos, fin de la maintenance.
     *
     * @param callable(array): array{code: int, sortie: string} $console lance une commande Symfony
     *
     * @return list<string> avertissements
     */
    public function finaliser(callable $console): array
    {
        $avertissements = [];
        try {
            $r = $console(['command' => 'doctrine:migrations:migrate', '--allow-no-migration' => true]);
            if (0 !== $r['code']) {
                throw new \RuntimeException("Les migrations de la base ont échoué :\n".$r['sortie']);
            }
            $r = $console(['command' => 'asset-map:compile']);
            if (0 !== $r['code']) {
                $avertissements[] = 'Les logos n\'ont pas pu être compilés : lancez « php bin/console asset-map:compile ».';
            }
        } finally {
            $this->maintenance->desactiver();
        }

        return $avertissements;
    }

    // ---------------------------------------------------------------------------

    private function telecharger(string $url, string $destination): void
    {
        $reponse = $this->httpClient->request('GET', $url, [
            'headers' => ['User-Agent' => 'GrimpePan/'.$this->version->get()],
            'timeout' => 30,
            'max_duration' => 600,
        ]);
        if (200 !== $reponse->getStatusCode()) {
            throw new \RuntimeException('Téléchargement impossible (HTTP '.$reponse->getStatusCode().').');
        }
        $f = fopen($destination, 'w');
        $taille = 0;
        foreach ($this->httpClient->stream($reponse) as $morceau) {
            $taille += \strlen($morceau->getContent());
            if ($taille > self::TAILLE_MAX) {
                fclose($f);
                @unlink($destination);
                throw new \RuntimeException('Archive trop volumineuse.');
            }
            fwrite($f, $morceau->getContent());
        }
        fclose($f);
    }

    /** Extrait l'archive et renvoie le dossier racine de l'application qu'elle contient. */
    private function extraire(string $zip, string $destination): string
    {
        $archive = new \ZipArchive();
        if (true !== $archive->open($zip)) {
            throw new \RuntimeException('Archive illisible.');
        }
        for ($i = 0; $i < $archive->numFiles; ++$i) {
            $nom = (string) $archive->getNameIndex($i);
            // Protection « zip slip » : aucun chemin absolu ni remontée de dossier
            if (str_starts_with($nom, '/') || str_contains($nom, '\\') || preg_match('#(^|/)\.\.(/|$)#', $nom)) {
                $archive->close();
                throw new \RuntimeException("Chemin interdit dans l'archive : $nom");
            }
        }
        $archive->extractTo($destination);
        $archive->close();

        // L'archive contient un dossier « grimpepan/ »
        $racine = is_file($destination.'/grimpepan/VERSION') ? $destination.'/grimpepan' : $destination;
        if (!is_file($racine.'/VERSION')) {
            throw new \RuntimeException('Archive invalide : fichier VERSION introuvable.');
        }

        return $racine;
    }

    /**
     * Remplace le code en place par celui de $nouvelle, élément par élément, en
     * déplaçant l'ancien dans var/mise-a-jour/precedente-X.Y.Z/. En cas d'erreur,
     * les éléments déjà remplacés sont remis en place.
     */
    private function remplacer(string $nouvelle): string
    {
        $precedente = $this->dossierTravail().'/precedente-'.$this->version->get().'-'.date('Ymd-His');
        $this->fs->mkdir([$precedente, $precedente.'/public']);

        $elements = [];
        foreach (scandir($nouvelle) as $nom) {
            if (!\in_array($nom, ['.', '..'], true) && !\in_array($nom, self::CONSERVES_RACINE, true)) {
                $elements[] = $nom;
            }
        }
        foreach (scandir($nouvelle.'/public') as $nom) {
            if (!\in_array($nom, ['.', '..'], true) && !\in_array($nom, self::CONSERVES_PUBLIC, true)) {
                $elements[] = 'public/'.$nom;
            }
        }

        $faits = [];
        try {
            foreach ($elements as $rel) {
                $cible = $this->racine.'/'.$rel;
                $sauvegarde = $precedente.'/'.$rel;
                $existait = file_exists($cible) || is_link($cible);
                if ($existait && !@rename($cible, $sauvegarde)) {
                    throw new \RuntimeException("Impossible de déplacer $rel (droits insuffisants ?).");
                }
                if (!@rename($nouvelle.'/'.$rel, $cible)) {
                    if ($existait) {
                        @rename($sauvegarde, $cible);
                    }
                    throw new \RuntimeException("Impossible d'installer $rel.");
                }
                $faits[] = [$rel, $existait];
            }
            // Règles de sécurité du dossier des photos (les photos elles-mêmes ne bougent pas)
            if (is_file($nouvelle.'/public/uploads/.htaccess')) {
                $this->fs->copy($nouvelle.'/public/uploads/.htaccess', $this->racine.'/public/uploads/.htaccess', true);
            }
            // Logos compilés de l'ancienne version : ils seront recompilés à la finalisation
            if (is_dir($this->racine.'/public/assets')) {
                @rename($this->racine.'/public/assets', $precedente.'/public/assets');
            }
        } catch (\Throwable $e) {
            foreach (array_reverse($faits) as [$rel, $existait]) {
                @rename($this->racine.'/'.$rel, $nouvelle.'/'.$rel);
                if ($existait) {
                    @rename($precedente.'/'.$rel, $this->racine.'/'.$rel);
                }
            }
            throw new \RuntimeException('Mise à jour annulée, l\'ancienne version est restée en place : '.$e->getMessage(), 0, $e);
        }

        return $precedente;
    }

    private function viderCaches(): void
    {
        $cache = $this->racine.'/var/cache/prod';
        if (is_dir($cache)) {
            $obsolete = $this->dossierTravail().'/cache-obsolete-'.bin2hex(random_bytes(4));
            @rename($cache, $obsolete) && $this->supprimerSansErreur($obsolete);
        }
        if (\function_exists('opcache_reset')) {
            @opcache_reset();
        }
        clearstatcache(true);
    }

    /** Garde seulement la sauvegarde de la version précédente. */
    private function nettoyerAnciennesSauvegardes(string $aGarder): void
    {
        foreach (glob($this->dossierTravail().'/precedente-*', GLOB_ONLYDIR) ?: [] as $d) {
            if ($d !== $aGarder) {
                $this->supprimerSansErreur($d);
            }
        }
    }

    private function supprimerSansErreur(string $chemin): void
    {
        try {
            $this->fs->remove($chemin);
        } catch (\Throwable) {
            // Fichiers appartenant à un autre utilisateur : sans gravité, ils restent dans var/
        }
    }
}
