<?php

namespace App\Entity;

use App\Enum\TypePrise;
use App\Repository\PriseRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Prise marquée sur la photo. Les coordonnées et le rayon sont exprimés
 * en pourcentage de la largeur/hauteur de l'image, pour rester justes
 * quelle que soit la taille d'affichage.
 */
#[ORM\Entity(repositoryClass: PriseRepository::class)]
class Prise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'prises')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Voie $voie = null;

    /** Position horizontale, en % de la largeur (0-100) */
    #[ORM\Column]
    private float $x = 0;

    /** Position verticale, en % de la hauteur (0-100) */
    #[ORM\Column]
    private float $y = 0;

    /** Rayon, en % de la largeur de l'image */
    #[ORM\Column]
    private float $rayon = 3;

    #[ORM\Column(enumType: TypePrise::class, length: 10)]
    private TypePrise $type = TypePrise::Main;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVoie(): ?Voie
    {
        return $this->voie;
    }

    public function setVoie(?Voie $voie): static
    {
        $this->voie = $voie;

        return $this;
    }

    public function getX(): float
    {
        return $this->x;
    }

    public function setX(float $x): static
    {
        $this->x = max(0, min(100, $x));

        return $this;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function setY(float $y): static
    {
        $this->y = max(0, min(100, $y));

        return $this;
    }

    public function getRayon(): float
    {
        return $this->rayon;
    }

    public function setRayon(float $rayon): static
    {
        $this->rayon = max(0.5, min(20, $rayon));

        return $this;
    }

    public function getType(): TypePrise
    {
        return $this->type;
    }

    public function setType(TypePrise $type): static
    {
        $this->type = $type;

        return $this;
    }

    /** @return array{x: float, y: float, r: float, type: string} */
    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y, 'r' => $this->rayon, 'type' => $this->type->value];
    }
}
