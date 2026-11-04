<?php

namespace App\Entity;

use App\Repository\PhotoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Photo d'une zone d'escalade. Plusieurs voies peuvent partager la même photo.
 */
#[ORM\Entity(repositoryClass: PhotoRepository::class)]
class Photo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Nom du fichier dans public/uploads/photos */
    #[ORM\Column(length: 255)]
    private ?string $fichier = null;

    #[ORM\Column]
    private int $largeur = 0;

    #[ORM\Column]
    private int $hauteur = 0;

    #[ORM\ManyToOne(inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Secteur $secteur = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $auteur = null;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    /** @var Collection<int, Voie> */
    #[ORM\OneToMany(targetEntity: Voie::class, mappedBy: 'photo', orphanRemoval: true)]
    private Collection $voies;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
        $this->voies = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getFichier(): ?string { return $this->fichier; }
    public function setFichier(string $fichier): static { $this->fichier = $fichier; return $this; }

    public function getLargeur(): int { return $this->largeur; }
    public function setLargeur(int $largeur): static { $this->largeur = $largeur; return $this; }

    public function getHauteur(): int { return $this->hauteur; }
    public function setHauteur(int $hauteur): static { $this->hauteur = $hauteur; return $this; }

    public function getSecteur(): ?Secteur { return $this->secteur; }
    public function setSecteur(?Secteur $secteur): static { $this->secteur = $secteur; return $this; }

    public function getAuteur(): ?User { return $this->auteur; }
    public function setAuteur(?User $auteur): static { $this->auteur = $auteur; return $this; }

    public function getCreeLe(): \DateTimeImmutable { return $this->creeLe; }

    /** @return Collection<int, Voie> */
    public function getVoies(): Collection { return $this->voies; }

    public function getChemin(): string
    {
        return 'uploads/photos/'.$this->fichier;
    }

    public function __toString(): string
    {
        return sprintf('Photo #%d du %s', $this->id, $this->creeLe->format('d/m/Y'));
    }
}
