<?php

namespace App\Command;

use App\Repository\PhotoRepository;
use App\Service\PhotoOptimiseur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Applique la réduction (2000 × 2000 max) aux photos déjà enregistrées.
 * Les prises étant stockées en pourcentage, elles restent bien placées.
 */
#[AsCommand(
    name: 'app:photos:reduire',
    description: 'Réduit les photos existantes à 2000 × 2000 maximum (proportions conservées)',
)]
class ReduirePhotosCommand
{
    public function __construct(
        private readonly PhotoRepository $photos,
        private readonly PhotoOptimiseur $optimiseur,
        private readonly EntityManagerInterface $em,
        private readonly string $photosDir,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Affiche ce qui serait fait, sans rien modifier')] bool $simulation = false,
    ): int {
        $max = PhotoOptimiseur::COTE_MAX;
        $aTraiter = $absentes = $avant = $apres = 0;

        foreach ($this->photos->findAll() as $photo) {
            $chemin = $this->photosDir.'/'.$photo->getFichier();
            if (!is_file($chemin)) {
                ++$absentes;
                $io->warning(sprintf('Fichier absent : %s (photo n° %d)', $photo->getFichier(), $photo->getId()));
                continue;
            }
            [$l, $h] = getimagesize($chemin) ?: [0, 0];
            if (max($l, $h) <= $max) {
                continue;
            }

            ++$aTraiter;
            $taille = filesize($chemin);
            $avant += $taille;
            if ($simulation) {
                $io->text(sprintf('%s : %d × %d, %s', $photo->getFichier(), $l, $h, $this->mo($taille)));
                continue;
            }

            $r = $this->optimiseur->optimiser($chemin);
            clearstatcache(true, $chemin);
            $apres += filesize($chemin);
            $photo->setLargeur($r['largeur'])->setHauteur($r['hauteur']);
            $io->text(sprintf('%s : %d × %d → %d × %d, %s → %s',
                $photo->getFichier(), $l, $h, $r['largeur'], $r['hauteur'], $this->mo($taille), $this->mo(filesize($chemin))));
        }

        if (!$simulation) {
            $this->em->flush();
        }

        if (0 === $aTraiter) {
            $io->success(sprintf('Aucune photo ne dépasse %d × %d.', $max, $max));
        } elseif ($simulation) {
            $io->note(sprintf('%d photo(s) à réduire (%s au total). Relancez sans --simulation pour les réduire.', $aTraiter, $this->mo($avant)));
        } else {
            $io->success(sprintf('%d photo(s) réduite(s) : %s → %s.', $aTraiter, $this->mo($avant), $this->mo($apres)));
        }

        return $absentes > 0 ? 1 : 0;
    }

    private function mo(int $octets): string
    {
        return number_format($octets / 1048576, 1, ',', ' ').' Mo';
    }
}
