<?php

namespace App\Service;

use App\Entity\Photo;
use App\Entity\Secteur;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Enregistre une photo envoyée (redressée et réduite, voir PhotoOptimiseur), crée
 * sa miniature (voir MiniaturesPhotos) et l'entité Photo correspondante.
 */
class PhotoUploader
{
    public function __construct(
        private readonly string $photosDir,
        private readonly PhotoOptimiseur $optimiseur,
        private readonly MiniaturesPhotos $miniatures,
    ) {
    }

    public function upload(UploadedFile $fichier, Secteur $secteur, User $auteur): Photo
    {
        $extension = $fichier->guessExtension() ?? 'jpg';
        $nom = bin2hex(random_bytes(12)).'.'.$extension;
        $fichier->move($this->photosDir, $nom);

        $resultat = $this->optimiseur->optimiser($this->photosDir.'/'.$nom);
        try {
            $this->miniatures->creer($nom);
        } catch (\Throwable) {
            // Sans miniature, la photo s'affiche en entier : l'envoi n'échoue pas pour autant
        }

        return (new Photo())
            ->setFichier($nom)
            ->setLargeur($resultat['largeur'])
            ->setHauteur($resultat['hauteur'])
            ->setSecteur($secteur)
            ->setAuteur($auteur);
    }
}
