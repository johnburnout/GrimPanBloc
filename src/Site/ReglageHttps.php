<?php

namespace App\Site;

use App\Repository\ReglageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Réglage « Toujours utiliser HTTPS », lu à chaque requête par ForcerHttpsSubscriber.
 *
 * La valeur est gardée dans le cache de l'application pour éviter une requête SQL
 * par page ; elle est effacée à l'enregistrement du réglage, et le cache entier
 * l'est à chaque mise à jour du site.
 */
final class ReglageHttps
{
    public const CLE = 'site.forcer_https';
    private const CLE_CACHE = 'reglage_forcer_https';

    public function __construct(
        private readonly ReglageRepository $reglages,
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * false si la base n'est pas encore installée ou migrée ; ce cas n'est pas mis
     * en cache, pour que le réglage soit pris en compte dès que la base est prête.
     */
    public function actif(): bool
    {
        try {
            return $this->cache->get(self::CLE_CACHE, fn (): bool => '1' === $this->reglages->valeur(self::CLE));
        } catch (\Throwable) {
            return false;
        }
    }

    public function definir(bool $actif): void
    {
        $this->reglages->definir(self::CLE, $actif ? '1' : '0');
        $this->em->flush();
        $this->cache->delete(self::CLE_CACHE);
    }
}
