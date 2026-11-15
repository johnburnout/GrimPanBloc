<?php

namespace App\Site;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Règlement intérieur de la salle (PDF), proposé en lien sur la page « Informations ».
 *
 * Rangé dans var/documents/ (conservé par les mises à jour) et non dans
 * public/uploads/, qui ne sert que des images : il est envoyé par
 * InformationsController, avec le type « application/pdf ».
 */
final class ReglementInterieur
{
    public const TAILLE_MAX = '10M';
    private const FICHIER = 'reglement-interieur.pdf';

    private readonly Filesystem $fs;

    public function __construct(
        #[Autowire('%documents_dir%')] private readonly string $dossier,
    ) {
        $this->fs = new Filesystem();
    }

    /** Chemin du PDF enregistré, ou null. */
    public function chemin(): ?string
    {
        $chemin = $this->dossier.'/'.self::FICHIER;

        return is_file($chemin) ? $chemin : null;
    }

    /** Date d'envoi du PDF (sert aussi à changer l'adresse du lien à chaque nouvelle version). */
    public function modifieLe(): ?\DateTimeImmutable
    {
        $chemin = $this->chemin();

        return $chemin ? (new \DateTimeImmutable())->setTimestamp((int) filemtime($chemin)) : null;
    }

    public function definir(UploadedFile $fichier): void
    {
        // Le type annoncé par le navigateur ne suffit pas : on lit la signature du fichier
        $debut = (string) @file_get_contents($fichier->getPathname(), false, null, 0, 5);
        if ('%PDF-' !== $debut) {
            throw new \InvalidArgumentException('Le règlement intérieur doit être un fichier PDF.');
        }

        $this->fs->mkdir($this->dossier);
        $temporaire = $this->dossier.'/.'.self::FICHIER.'.'.bin2hex(random_bytes(4));
        $this->fs->copy($fichier->getPathname(), $temporaire, true);
        $this->fs->rename($temporaire, $this->dossier.'/'.self::FICHIER, true);
        clearstatcache();
    }

    public function supprimer(): void
    {
        $this->fs->remove($this->dossier.'/'.self::FICHIER);
        clearstatcache();
    }
}
