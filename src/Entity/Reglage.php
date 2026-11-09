<?php

namespace App\Entity;

use App\Repository\ReglageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Réglage du site modifiable depuis l'administration (clé → valeur).
 * Stocké en base pour être modifiable sans accès aux fichiers du serveur.
 */
#[ORM\Entity(repositoryClass: ReglageRepository::class)]
class Reglage
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 100)]
        private string $cle,

        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $valeur = null,
    ) {
    }

    public function getCle(): string
    {
        return $this->cle;
    }

    public function getValeur(): ?string
    {
        return $this->valeur;
    }

    public function setValeur(?string $valeur): static
    {
        $this->valeur = $valeur;

        return $this;
    }
}
