<?php

namespace App\Sauvegarde;

use App\Repository\ReglageRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Nombre de sauvegardes automatiques conservées (Administration → Sauvegardes). */
final class ReglageSauvegardes
{
    public const CLE = 'sauvegardes.automatiques_gardees';
    public const MIN = 3;
    public const MAX = 10;
    public const DEFAUT = 5;

    public function __construct(
        private readonly ReglageRepository $reglages,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function automatiquesGardees(): int
    {
        $valeur = $this->reglages->valeur(self::CLE);

        return null === $valeur || !ctype_digit($valeur) ? self::DEFAUT : max(self::MIN, min(self::MAX, (int) $valeur));
    }

    public function definirAutomatiquesGardees(int $nombre): void
    {
        if ($nombre < self::MIN || $nombre > self::MAX) {
            throw new \InvalidArgumentException(\sprintf('Entre %d et %d sauvegardes automatiques.', self::MIN, self::MAX));
        }
        $this->reglages->definir(self::CLE, (string) $nombre);
        $this->em->flush();
    }
}
