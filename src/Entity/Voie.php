<?php

namespace App\Entity;

use App\Enum\CotationCouleur;
use App\Enum\CotationFr;
use App\Enum\SystemeCotation;
use App\Repository\VoieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: VoieRepository::class)]
#[ORM\Index(columns: ['systeme', 'rang'])]
class Voie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'voies')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Photo $photo = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $auteur = null;

    #[ORM\Column(enumType: SystemeCotation::class, length: 10)]
    private SystemeCotation $systeme = SystemeCotation::Francaise;

    /** Valeur brute de la cotation : "6a", "bleu"… */
    #[ORM\Column(length: 10)]
    private string $cotation = '';

    /** Rang numérique de difficulté dans son échelle, pour trier et filtrer. */
    #[ORM\Column]
    private int $rang = 0;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    /** @var Collection<int, Prise> */
    #[ORM\OneToMany(targetEntity: Prise::class, mappedBy: 'voie', cascade: ['persist'], orphanRemoval: true)]
    private Collection $prises;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
        $this->prises = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getPhoto(): ?Photo { return $this->photo; }
    public function setPhoto(?Photo $photo): static { $this->photo = $photo; return $this; }

    public function getAuteur(): ?User { return $this->auteur; }
    public function setAuteur(?User $auteur): static { $this->auteur = $auteur; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }

    public function getCreeLe(): \DateTimeImmutable { return $this->creeLe; }

    // --- Cotation -------------------------------------------------------

    public function getSysteme(): SystemeCotation { return $this->systeme; }
    public function setSysteme(SystemeCotation $systeme): static { $this->systeme = $systeme; return $this; }

    public function getCotation(): string { return $this->cotation; }
    public function getRang(): int { return $this->rang; }

    public function getCotationFr(): ?CotationFr
    {
        return SystemeCotation::Francaise === $this->systeme ? CotationFr::tryFrom($this->cotation) : null;
    }

    public function setCotationFr(?CotationFr $c): static
    {
        if (null !== $c && SystemeCotation::Francaise === $this->systeme) {
            $this->cotation = $c->value;
            $this->rang = $c->rang();
        }

        return $this;
    }

    public function getCotationCouleur(): ?CotationCouleur
    {
        return SystemeCotation::Couleur === $this->systeme ? CotationCouleur::tryFrom($this->cotation) : null;
    }

    public function setCotationCouleur(?CotationCouleur $c): static
    {
        if (null !== $c && SystemeCotation::Couleur === $this->systeme) {
            $this->cotation = $c->value;
            $this->rang = $c->rang();
        }

        return $this;
    }

    /** L'enum de cotation correspondant au système choisi. */
    public function getCotationEnum(): CotationFr|CotationCouleur|null
    {
        return $this->getCotationFr() ?? $this->getCotationCouleur();
    }

    #[Assert\Callback]
    public function validerCotation(ExecutionContextInterface $context): void
    {
        if (null === $this->getCotationEnum()) {
            $champ = SystemeCotation::Francaise === $this->systeme ? 'cotationFr' : 'cotationCouleur';
            $context->buildViolation('Choisissez une cotation.')->atPath($champ)->addViolation();
        }
    }

    // --- Prises ---------------------------------------------------------

    /** @return Collection<int, Prise> */
    public function getPrises(): Collection { return $this->prises; }

    public function addPrise(Prise $prise): static
    {
        if (!$this->prises->contains($prise)) {
            $this->prises->add($prise);
            $prise->setVoie($this);
        }

        return $this;
    }

    public function removePrise(Prise $prise): static
    {
        $this->prises->removeElement($prise);

        return $this;
    }

    /** @return list<array{x: float, y: float, r: float, type: string}> */
    public function getPrisesArray(): array
    {
        return array_values($this->prises->map(fn (Prise $p) => $p->toArray())->toArray());
    }
}
