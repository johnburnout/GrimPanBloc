<?php

namespace App\Service;

use App\Entity\Secteur;
use App\Entity\User;
use App\Entity\Voie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Enregistrement et suppression des voies (le formulaire a déjà été validé). */
final class GestionVoie
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PhotoUploader $uploader,
    ) {
    }

    /** Enregistre la voie, après avoir rattaché la nouvelle photo envoyée s'il y en a une. */
    public function enregistrer(Voie $voie, Secteur $secteur, User $auteurPhoto, ?UploadedFile $nouvellePhoto = null): void
    {
        if (null !== $nouvellePhoto) {
            $photo = $this->uploader->upload($nouvellePhoto, $secteur, $auteurPhoto);
            $this->em->persist($photo);
            $voie->setPhoto($photo);
        }
        $this->em->persist($voie);
        $this->em->flush();
    }

    /** Supprime la voie, ses prises et ses croix. */
    public function supprimer(Voie $voie): void
    {
        $this->em->remove($voie);
        $this->em->flush();
    }
}
