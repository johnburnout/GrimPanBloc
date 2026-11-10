<?php

namespace App\Sauvegarde;

use App\Enum\TypeSauvegarde;

/** Une archive du dossier des sauvegardes (voir DepotSauvegardes). */
final readonly class Sauvegarde
{
    public function __construct(
        public string $nom,
        public string $chemin,
        public TypeSauvegarde $type,
        public \DateTimeImmutable $creeLe,
        public int $taille,
    ) {
    }
}
