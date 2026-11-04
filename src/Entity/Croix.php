<?php

namespace App\Entity;

use App\Repository\CroixRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * « Croix » : un grimpeur indique qu'il a réussi la voie.
 * Une croix posée par un autre grimpeur que l'auteur valide la voie.
 */
#[ORM\Entity(repositoryClass: CroixRepository::class)]
#[ORM\UniqueConstraint(name: 'croix_unique', columns: ['voie_id', 'grimpeur_id'])]
class Croix
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'croix')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Voie $voie;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $grimpeur;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    public function __construct(Voie $voie, User $grimpeur)
    {
        $this->voie = $voie;
        $this->grimpeur = $grimpeur;
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getVoie(): Voie { return $this->voie; }
    public function getGrimpeur(): User { return $this->grimpeur; }
    public function getCreeLe(): \DateTimeImmutable { return $this->creeLe; }
}
