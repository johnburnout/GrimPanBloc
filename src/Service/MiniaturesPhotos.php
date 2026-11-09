<?php

namespace App\Service;

use App\Entity\Photo;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Miniatures des photos (800 px de côté au plus), pour les listes de voies et
 * l'administration : quelques dizaines de Ko au lieu de la photo entière.
 *
 * Rangées dans uploads/photos/miniatures/, sous le même nom que la photo. Une
 * photo sans miniature (petite photo, ou miniature pas encore générée) s'affiche
 * en entier : rien ne casse si la génération échoue.
 */
final class MiniaturesPhotos
{
    public const COTE = 800;
    public const DOSSIER = 'miniatures';

    public function __construct(
        private readonly PhotoOptimiseur $optimiseur,
        #[Autowire('%photos_dir%')] private readonly string $photosDir,
    ) {
    }

    /** Chemin public de la miniature, ou de la photo elle-même à défaut. */
    public function chemin(Photo $photo): string
    {
        return is_file($this->fichier((string) $photo->getFichier()))
            ? 'uploads/photos/'.self::DOSSIER.'/'.$photo->getFichier()
            : $photo->getChemin();
    }

    /** Crée (ou recrée) la miniature ; false si la photo est déjà assez petite ou illisible. */
    public function creer(string $fichier): bool
    {
        return $this->optimiseur->miniature($this->photosDir.'/'.$fichier, $this->fichier($fichier), self::COTE);
    }

    public function existe(string $fichier): bool
    {
        return is_file($this->fichier($fichier));
    }

    public function supprimer(string $fichier): void
    {
        if (is_file($chemin = $this->fichier($fichier))) {
            @unlink($chemin);
        }
    }

    private function fichier(string $fichier): string
    {
        return $this->photosDir.'/'.self::DOSSIER.'/'.$fichier;
    }
}
