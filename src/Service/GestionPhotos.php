<?php

namespace App\Service;

use App\Entity\Photo;
use App\Entity\Secteur;
use App\Entity\User;
use App\Exception\OperationRefusee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Photos d'un secteur : ajout par un administrateur (page du secteur), suppression de
 * celles qu'aucune voie n'utilise (Administration → Photos).
 */
final class GestionPhotos
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MiniaturesPhotos $miniatures,
        private readonly PhotoUploader $uploader,
        #[Autowire('%photos_dir%')] private readonly string $photosDir,
    ) {
    }

    /**
     * Enregistre les photos envoyées dans le secteur, sans voie : elles sont ensuite
     * proposées à la création d'une voie.
     *
     * @param iterable<UploadedFile> $fichiers
     *
     * @return int nombre de photos ajoutées
     */
    public function ajouter(Secteur $secteur, iterable $fichiers, User $auteur): int
    {
        $n = 0;
        foreach ($fichiers as $fichier) {
            $this->em->persist($this->uploader->upload($fichier, $secteur, $auteur));
            ++$n;
        }
        $this->em->flush();

        return $n;
    }

    /**
     * Supprime la photo, son fichier et sa miniature.
     *
     * @throws \LogicException si une voie l'utilise encore
     */
    public function supprimer(Photo $photo): void
    {
        if (!$photo->getVoies()->isEmpty()) {
            throw new OperationRefusee('erreur.photo_utilisee');
        }
        $fichier = $this->photosDir.'/'.$photo->getFichier();

        $this->em->remove($photo);
        $this->em->flush();
        // Après la base : en cas d'échec, mieux vaut un fichier orphelin qu'une photo sans fichier
        if (is_file($fichier)) {
            @unlink($fichier);
        }
        $this->miniatures->supprimer((string) $photo->getFichier());
    }
}
