<?php

namespace App\MiseAJour;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Étapes 1 et 2 d'une mise à jour (voir InstallateurMiseAJour) : télécharge
 * l'archive d'une release, vérifie son empreinte SHA-256, l'extrait et contrôle
 * son contenu. Rien n'est encore modifié dans l'installation en place.
 */
final class ArchiveRelease
{
    private const TAILLE_MAX = 300 * 1024 * 1024;
    /** Fichiers sans lesquels la nouvelle version ne peut pas démarrer. */
    private const INDISPENSABLES = ['vendor/autoload_runtime.php', 'bin/console', 'public/index.php', 'src/Kernel.php'];
    /** Dossier racine de l'archive : « grimpanbloc/ » (« grimpepan/ » avant la 1.3.0). */
    private const DOSSIERS_RACINE = ['grimpanbloc', 'grimpepan'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly VersionInstallee $version,
    ) {
    }

    /**
     * Télécharge l'archive dans $zip et vérifie son empreinte.
     *
     * @return int taille de l'archive, en octets
     */
    public function telecharger(Release $release, string $zip): int
    {
        $this->telechargerFichier((string) $release->urlArchive, $zip);
        $attendue = strtolower(strtok(trim($this->httpClient->request('GET', (string) $release->urlEmpreinte, ['timeout' => 20])->getContent()), " \t"));
        $obtenue = hash_file('sha256', $zip);
        if (!preg_match('/^[0-9a-f]{64}$/', $attendue) || !hash_equals($attendue, $obtenue)) {
            @unlink($zip);
            throw new \RuntimeException('L\'empreinte SHA-256 de l\'archive ne correspond pas : téléchargement corrompu ou modifié.');
        }

        return (int) filesize($zip);
    }

    /** Extrait l'archive dans $destination, la contrôle, et renvoie le dossier de l'application qu'elle contient. */
    public function extraire(Release $release, string $zip, string $destination): string
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

        $racine = $destination;
        foreach (self::DOSSIERS_RACINE as $dossier) {
            if (is_file("$destination/$dossier/VERSION")) {
                $racine = "$destination/$dossier";
                break;
            }
        }
        if (!is_file($racine.'/VERSION')) {
            throw new \RuntimeException('Archive invalide : fichier VERSION introuvable.');
        }

        $versionArchive = trim((string) @file_get_contents($racine.'/VERSION'));
        if ($versionArchive !== $release->version) {
            throw new \RuntimeException("L'archive annonce la version « $versionArchive » au lieu de {$release->version}.");
        }
        foreach (self::INDISPENSABLES as $f) {
            if (!is_file($racine.'/'.$f)) {
                throw new \RuntimeException("Archive incomplète : $f est absent.");
            }
        }

        return $racine;
    }

    private function telechargerFichier(string $url, string $destination): void
    {
        $reponse = $this->httpClient->request('GET', $url, [
            'headers' => ['User-Agent' => 'GrimPanBloc/'.$this->version->get()],
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
}
