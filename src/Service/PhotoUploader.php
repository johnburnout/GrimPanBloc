<?php

namespace App\Service;

use App\Entity\Photo;
use App\Entity\Secteur;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Enregistre une photo envoyée et crée l'entité Photo correspondante.
 */
class PhotoUploader
{
    public function __construct(private readonly string $photosDir)
    {
    }

    public function upload(UploadedFile $fichier, Secteur $secteur, User $auteur): Photo
    {
        $extension = $fichier->guessExtension() ?? 'jpg';
        $nom = bin2hex(random_bytes(12)).'.'.$extension;
        $fichier->move($this->photosDir, $nom);

        $chemin = $this->photosDir.'/'.$nom;
        $this->corrigerOrientation($chemin);
        [$largeur, $hauteur] = getimagesize($chemin) ?: [0, 0];

        return (new Photo())
            ->setFichier($nom)
            ->setLargeur($largeur)
            ->setHauteur($hauteur)
            ->setSecteur($secteur)
            ->setAuteur($auteur);
    }

    /**
     * Les photos de téléphone sont souvent stockées "couchées" avec une
     * indication EXIF. On les redresse pour que les coordonnées des prises
     * correspondent à ce que voit le navigateur.
     */
    private function corrigerOrientation(string $chemin): void
    {
        if (!\function_exists('exif_read_data') || !\function_exists('imagecreatefromjpeg')) {
            return;
        }
        if (IMAGETYPE_JPEG !== @exif_imagetype($chemin)) {
            return;
        }
        $exif = @exif_read_data($chemin);
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if (0 === $angle) {
            return;
        }
        $image = imagecreatefromjpeg($chemin);
        if (false === $image) {
            return;
        }
        $image = imagerotate($image, $angle, 0);
        imagejpeg($image, $chemin, 90);
    }
}
