<?php

namespace App\Twig;

use App\MiseAJour\Release;
use App\MiseAJour\VerificateurMiseAJour;
use App\MiseAJour\VersionInstallee;
use Twig\Attribute\AsTwigFunction;

/** Fonctions Twig : version installée et mise à jour disponible (bandeau des admins). */
final class MiseAJourExtension
{
    public function __construct(
        private readonly VersionInstallee $version,
        private readonly VerificateurMiseAJour $verificateur,
    ) {
    }

    #[AsTwigFunction('grimpanbloc_version')]
    public function version(): string
    {
        return $this->version->get();
    }

    #[AsTwigFunction('grimpanbloc_mise_a_jour')]
    public function miseAJour(): ?Release
    {
        return $this->verificateur->miseAJourDisponible();
    }
}
