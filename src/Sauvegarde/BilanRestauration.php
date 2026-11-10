<?php

namespace App\Sauvegarde;

/** Ce qu'une restauration (ou un nettoyage) a fait. */
final readonly class BilanRestauration
{
    public function __construct(
        public int $secteurs,
        public int $photos,
        public int $voies,
        public int $croix,
        public int $croixIgnorees,
        public int $photosSansFichier,
        public int $voiesSupprimees,
        public int $photosSupprimees,
    ) {
    }
}
