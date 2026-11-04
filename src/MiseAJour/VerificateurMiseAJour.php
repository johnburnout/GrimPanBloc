<?php

namespace App\MiseAJour;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Interroge GitHub (au plus une fois par jour) pour savoir si une release plus
 * récente que la version installée a été publiée.
 */
final class VerificateurMiseAJour
{
    private const CLE_CACHE = 'grimpepan_derniere_release';
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
                    'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'GrimpePan/'.$this->version->get()],
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
                $this->logger->warning('Vérification des mises à jour impossible : {message}', ['message' => $e->getMessage()]);
                $item->expiresAfter(self::DUREE_CACHE_ECHEC);

                return null;
            }
        });

        return \is_array($donnees) && isset($donnees['tag_name']) ? Release::depuisApi($donnees) : null;
    }

    /** Release plus récente que la version installée, ou null. */
    public function miseAJourDisponible(bool $forcer = false): ?Release
    {
        $release = $this->derniereRelease($forcer);

        return $release && $this->version->estDepasseePar($release->version) ? $release : null;
    }
}
