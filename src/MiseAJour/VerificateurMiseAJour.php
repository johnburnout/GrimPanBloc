<?php

namespace App\MiseAJour;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Interroge GitHub (au plus une fois par jour) pour savoir si une release plus
 * récente que la version installée a été publiée.
 *
 * Sans authentification, l'API GitHub n'accepte que 60 requêtes par heure et par
 * adresse IP : sur un hébergement mutualisé, d'autres sites qui partagent l'adresse
 * peuvent épuiser ce quota (réponse 403). La release est alors lue sur le site
 * github.com, qui n'a pas cette limite : la page « latest » redirige vers le tag,
 * les archives suivent la convention de nommage (voir Release) et les notes sont
 * la section du tag dans CHANGELOG.md, comme dans .github/workflows/release.yml.
 */
final class VerificateurMiseAJour
{
    private const CLE_CACHE = 'grimpanbloc_derniere_release';
    private const DUREE_CACHE = 86400;      // 24 h
    private const DUREE_CACHE_ECHEC = 3600; // GitHub injoignable : on réessaie dans 1 h

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly VersionInstallee $version,
        private readonly LoggerInterface $logger,
        private readonly string $apiReleases,
    ) {
    }

    /** Dernière release publiée (hors brouillons et préversions), ou null si inconnue. */
    public function derniereRelease(bool $forcer = false): ?Release
    {
        if ($forcer) {
            $this->cache->delete(self::CLE_CACHE);
        }
        $donnees = $this->cache->get(self::CLE_CACHE, function (ItemInterface $item): ?array {
            try {
                $reponse = $this->httpClient->request('GET', rtrim($this->apiReleases, '/').'/releases/latest', [
                    'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'GrimPanBloc/'.$this->version->get()],
                    'timeout' => 4,
                    'max_duration' => 8,
                ]);
                if (404 === $reponse->getStatusCode()) { // aucune release publiée
                    $item->expiresAfter(self::DUREE_CACHE);

                    return null;
                }
                $item->expiresAfter(self::DUREE_CACHE);

                return $reponse->toArray();
            } catch (\Throwable $e) {
                $this->logger->warning('API GitHub des releases indisponible : {message}', ['message' => $e->getMessage()]);
            }

            try {
                $donnees = $this->depuisSiteGithub();
                $item->expiresAfter($donnees ? self::DUREE_CACHE : self::DUREE_CACHE_ECHEC);

                return $donnees;
            } catch (\Throwable $e) {
                $this->logger->warning('Vérification des mises à jour impossible : {message}', ['message' => $e->getMessage()]);
                $item->expiresAfter(self::DUREE_CACHE_ECHEC);

                return null;
            }
        });

        return \is_array($donnees) && isset($donnees['tag_name']) ? Release::depuisApi($donnees) : null;
    }

    /**
     * Dernière release lue sur github.com, au format de l'API ; null si le dépôt
     * n'est pas sur GitHub ou n'a aucune release.
     *
     * @return array<string, mixed>|null
     */
    private function depuisSiteGithub(): ?array
    {
        if (!preg_match('#^https://api\.github\.com/repos/([\w.-]+/[\w.-]+)/?$#', $this->apiReleases, $m)) {
            return null;
        }
        $depot = $m[1];
        $entetes = ['User-Agent' => 'GrimPanBloc/'.$this->version->get()];
        $options = ['headers' => $entetes, 'timeout' => 4, 'max_duration' => 8];

        // « latest » redirige vers /releases/tag/vX.Y.Z (vers /releases s'il n'y en a aucune)
        $reponse = $this->httpClient->request('GET', "https://github.com/$depot/releases/latest", $options + ['max_redirects' => 0]);
        $destination = $reponse->getHeaders(false)['location'][0] ?? '';
        if (!preg_match('#/releases/tag/(v\d+\.\d+\.\d+)$#', $destination, $t)) {
            return null;
        }
        $tag = $t[1];

        $notes = '';
        try {
            $journal = $this->httpClient->request('GET', "https://raw.githubusercontent.com/$depot/$tag/CHANGELOG.md", $options)->getContent();
            $version = preg_quote(substr($tag, 1), '/');
            if (preg_match('/^## \['.$version.'\][^\n]*\n(.*?)(?=^## \[|\z)/ms', $journal, $section)) {
                $notes = trim($section[1]);
            }
        } catch (\Throwable $e) {
            $this->logger->info('Notes de version introuvables : {message}', ['message' => $e->getMessage()]);
        }

        $telechargement = "https://github.com/$depot/releases/download/$tag/grimpanbloc-$tag.zip";

        return [
            'tag_name' => $tag,
            'name' => 'GrimPanBloc '.substr($tag, 1),
            'body' => $notes,
            'html_url' => "https://github.com/$depot/releases/tag/$tag",
            'assets' => [
                ['name' => "grimpanbloc-$tag.zip", 'browser_download_url' => $telechargement],
                ['name' => "grimpanbloc-$tag.zip.sha256", 'browser_download_url' => $telechargement.'.sha256'],
            ],
        ];
    }

    /** Release plus récente que la version installée, ou null. */
    public function miseAJourDisponible(bool $forcer = false): ?Release
    {
        $release = $this->derniereRelease($forcer);

        return $release && $this->version->estDepasseePar($release->version) ? $release : null;
    }
}
