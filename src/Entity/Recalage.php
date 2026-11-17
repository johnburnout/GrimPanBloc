<?php

namespace App\Entity;

use App\Recalage\Homographie;
use App\Recalage\ModeleRecalage;
use App\Repository\RecalageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Passage d'une photo à une nouvelle photo du même mur.
 *
 * Quand un créateur change la photo de sa voie, la position de chacune de ses prises
 * sur l'ancienne photo et celle qu'il a choisie sur la nouvelle forment des paires de
 * points. La matrice (homographie) estimée sur ces paires est enregistrée, et sert à
 * proposer aux autres voies de l'ancienne photo de passer sur la nouvelle, prises
 * replacées. Chaque voie qui adopte la nouvelle photo apporte ses paires et affine
 * le recalage.
 */
#[ORM\Entity(repositoryClass: RecalageRepository::class)]
#[ORM\UniqueConstraint(columns: ['photo_source_id', 'photo_cible_id'])]
class Recalage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Photo $photoSource;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Photo $photoCible;

    /**
     * Paires de points, par voie : position sur l'ancienne photo (s) et sur la nouvelle (c), en %.
     *
     * @var list<array{voie: int|null, s: array{float, float}, c: array{float, float}}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $paires = [];

    /** @var list<float> homographie 3×3, ligne par ligne */
    #[ORM\Column(type: Types::JSON)]
    private array $matrice = [];

    /** @var Collection<int, Voie> voies dont le créateur a refusé la nouvelle photo */
    #[ORM\ManyToMany(targetEntity: Voie::class)]
    #[ORM\JoinTable(name: 'recalage_voie_ignoree')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $voiesIgnorees;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    #[ORM\Column]
    private \DateTimeImmutable $majLe;

    public function __construct(Photo $photoSource, Photo $photoCible)
    {
        $this->photoSource = $photoSource;
        $this->photoCible = $photoCible;
        $this->matrice = Homographie::identite();
        $this->voiesIgnorees = new ArrayCollection();
        $this->creeLe = $this->majLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhotoSource(): Photo
    {
        return $this->photoSource;
    }

    public function getPhotoCible(): Photo
    {
        return $this->photoCible;
    }

    /** @return list<array{voie: int|null, s: array{float, float}, c: array{float, float}}> */
    public function getPaires(): array
    {
        return $this->paires;
    }

    /** @return list<float> */
    public function getMatrice(): array
    {
        return $this->matrice;
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }

    public function getMajLe(): \DateTimeImmutable
    {
        return $this->majLe;
    }

    /**
     * Remplace les paires apportées par la voie (une voie ré-enregistrée ne compte
     * qu'une fois) et recalcule la matrice.
     *
     * @param list<array{0: float, 1: float, 2: float, 3: float}> $paires [xSource, ySource, xCible, yCible]
     */
    public function enregistrerPaires(Voie $voie, array $paires): static
    {
        $id = $voie->getId();
        $gardees = array_values(array_filter($this->paires, fn (array $p) => null === $id || $p['voie'] !== $id));
        foreach ($paires as [$xs, $ys, $xc, $yc]) {
            $gardees[] = ['voie' => $id, 's' => [round($xs, 2), round($ys, 2)], 'c' => [round($xc, 2), round($yc, 2)]];
        }
        $this->paires = $gardees;
        $this->matrice = Homographie::estimer($this->pairesPlates());
        $this->majLe = new \DateTimeImmutable();

        return $this;
    }

    public function modele(): ModeleRecalage
    {
        return new ModeleRecalage($this->matrice, $this->pairesPlates());
    }

    /** @return Collection<int, Voie> */
    public function getVoiesIgnorees(): Collection
    {
        return $this->voiesIgnorees;
    }

    public function ignorer(Voie $voie): static
    {
        if (!$this->voiesIgnorees->contains($voie)) {
            $this->voiesIgnorees->add($voie);
        }

        return $this;
    }

    /** @return list<array{0: float, 1: float, 2: float, 3: float}> */
    private function pairesPlates(): array
    {
        return array_map(fn (array $p) => [(float) $p['s'][0], (float) $p['s'][1], (float) $p['c'][0], (float) $p['c'][1]], $this->paires);
    }
}
