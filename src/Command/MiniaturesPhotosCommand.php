<?php

namespace App\Command;

use App\Repository\PhotoRepository;
use App\Service\MiniaturesPhotos;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Crée les miniatures manquantes (photos envoyées avant leur introduction).
 * Lancée aussi à la fin de chaque mise à jour.
 */
#[AsCommand(
    name: 'app:photos:miniatures',
    description: 'Crée les miniatures manquantes des photos (800 px de côté au plus)',
)]
class MiniaturesPhotosCommand
{
    public function __construct(
        private readonly PhotoRepository $photos,
        private readonly MiniaturesPhotos $miniatures,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Recrée aussi les miniatures existantes')] bool $toutes = false,
    ): int {
        $creees = $echecs = 0;
        foreach ($this->photos->findAll() as $photo) {
            $fichier = (string) $photo->getFichier();
            if (!$toutes && $this->miniatures->existe($fichier)) {
                continue;
            }
            try {
                if ($this->miniatures->creer($fichier)) {
                    ++$creees;
                }
            } catch (\Throwable $e) {
                ++$echecs;
                $io->warning(\sprintf('%s : %s', $fichier, $e->getMessage()));
            }
        }

        $io->success(0 === $creees ? 'Aucune miniature à créer.' : \sprintf('%d miniature(s) créée(s).', $creees));

        return $echecs > 0 ? 1 : 0;
    }
}
