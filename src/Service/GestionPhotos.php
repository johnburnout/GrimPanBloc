<?php

namespace App\Service;

use App\Entity\Photo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Suppression des photos qu'aucune voie n'utilise (Administration → Photos). */
final class GestionPhotos
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%photos_dir%')] private readonly string $photosDir,
    ) {
    }

    /**
     * Supprime la photo et son fichier.
     *
     * @throws \LogicException si une voie l'utilise encore
     */
    public function supprimer(Photo $photo): void
    {
        if (!$photo->getVoies()->isEmpty()) {
            throw new \LogicException('Cette photo est utilisée par une voie : supprimez ou modifiez d\'abord la voie.');
        }
        $fichier = $this->photosDir.'/'.$photo->getFichier();

        $this->em->remove($photo);
        $this->em->flush();
        // Après la base : en cas d'échec, mieux vaut un fichier orphelin qu'une photo sans fichier
        if (is_file($fichier)) {
            @unlink($fichier);
        }
    }
}
