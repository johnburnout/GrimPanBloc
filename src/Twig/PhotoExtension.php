<?php

namespace App\Twig;

use App\Entity\Photo;
use App\Service\MiniaturesPhotos;
use Twig\Attribute\AsTwigFunction;

/** Fonction Twig miniature(photo) : chemin de la miniature, ou de la photo à défaut. */
final class PhotoExtension
{
    public function __construct(private readonly MiniaturesPhotos $miniatures)
    {
    }

    #[AsTwigFunction('miniature')]
    public function miniature(Photo $photo): string
    {
        return $this->miniatures->chemin($photo);
    }
}
