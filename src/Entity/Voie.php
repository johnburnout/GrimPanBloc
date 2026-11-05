<?php

namespace App\Entity;

use App\Cotation\ConsensusCotation;
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

    /** Cotation affichée (consensus), valeur brute : "6a", "bleu"… */
    #[ORM\Column(length: 10)]
    private string $cotation = '';

    /** Cotation proposée par le créateur. */
    #[ORM\Column(length: 10)]
    private string $cotationAuteur = '';

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

    /** @var Collection<int, Croix> */
    #[ORM\OneToMany(targetEntity: Croix::class, mappedBy: 'voie')]
    #[ORM\OrderBy(['creeLe' => 'ASC'])]
    private Collection $croix;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
        $this->prises = new ArrayCollection();
        $this->croix = new ArrayCollection();
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
    //
    // « cotationAuteur » est la proposition du créateur ; « cotation » et « rang »
    // sont la cotation AFFICHÉE (consensus des validateurs, voir ConsensusCotation),
    // recalculée à chaque changement et utilisée pour trier et filtrer.

    public function getSysteme(): SystemeCotation { return $this->systeme; }

    public function setSysteme(SystemeCotation $systeme): static
    {
        $this->systeme = $systeme;
        $this->recalculerCotation();

        return $this;
    }

    /** Cotation affichée (valeur brute : « 6a », « bleu »). */
    public function getCotation(): string { return $this->cotation; }
    public function getRang(): int { return $this->rang; }

    /** Proposition du créateur (valeur brute). */
    public function getCotationAuteur(): string { return $this->cotationAuteur; }

    // Champs du formulaire : ils portent la proposition du créateur.
    public function getCotationFr(): ?CotationFr
    {
        return SystemeCotation::Francaise === $this->systeme ? CotationFr::tryFrom($this->cotationAuteur) : null;
    }

    public function setCotationFr(?CotationFr $c): static
    {
        if (null !== $c && SystemeCotation::Francaise === $this->systeme) {
            $this->setCotationAuteur($c->value);
        }

        return $this;
    }

    public function getCotationCouleur(): ?CotationCouleur
    {
        return SystemeCotation::Couleur === $this->systeme ? CotationCouleur::tryFrom($this->cotationAuteur) : null;
    }

    public function setCotationCouleur(?CotationCouleur $c): static
    {
        if (null !== $c && SystemeCotation::Couleur === $this->systeme) {
            $this->setCotationAuteur($c->value);
        }

        return $this;
    }

    public function setCotationAuteur(string $valeur): static
    {
        $this->cotationAuteur = $valeur;
        $this->recalculerCotation();

        return $this;
    }

    /** Cotation affichée, sous forme d'enum de l'échelle de la voie. */
    public function getCotationEnum(): CotationFr|CotationCouleur|null
    {
        return $this->systeme->cotation($this->cotation);
    }

    public function getCotationAuteurEnum(): CotationFr|CotationCouleur|null
    {
        return $this->systeme->cotation($this->cotationAuteur);
    }

    /**
     * Propositions des validateurs (cotations valides dans l'échelle de la voie).
     *
     * @return list<string>
     */
    public function getVotesCotation(): array
    {
        $votes = [];
        foreach ($this->getCroixValidantes() as $c) {
            if (null !== $this->systeme->cotation($c->getCotation())) {
                $votes[] = $c->getCotation();
            }
        }

        return $votes;
    }

    /**
     * Nombre de propositions par cotation, de la plus facile à la plus dure.
     *
     * @return list<array{cotation: CotationFr|CotationCouleur, nombre: int}>
     */
    public function getRepartitionCotations(): array
    {
        $r = [];
        foreach (ConsensusCotation::repartition($this->getVotesCotation(), $this->systeme->rang(...)) as $valeur => $n) {
            $r[] = ['cotation' => $this->systeme->cotation((string) $valeur), 'nombre' => $n];
        }

        return $r;
    }

    /** Recalcule la cotation affichée à partir des propositions. */
    public function recalculerCotation(): void
    {
        $this->cotation = ConsensusCotation::calculer($this->getVotesCotation(), $this->cotationAuteur, $this->systeme->rang(...));
        $this->rang = $this->systeme->rang($this->cotation);
    }

    #[Assert\Callback]
    public function validerCotation(ExecutionContextInterface $context): void
    {
        if (null === $this->getCotationAuteurEnum()) {
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

    /** Remplace toutes les prises (les anciennes sont supprimées par orphanRemoval). */
    public function remplacerPrises(iterable $prises): static
    {
        foreach ($this->prises->toArray() as $ancienne) {
            $this->removePrise($ancienne);
        }
        foreach ($prises as $prise) {
            $this->addPrise($prise);
        }

        return $this;
    }

    // --- Croix (réussites) -----------------------------------------------

    /** @return Collection<int, Croix> */
    public function getCroix(): Collection { return $this->croix; }

    /** Croix posées par d'autres grimpeurs que l'auteur. */
    public function getCroixValidantes(): array
    {
        return array_values($this->croix->filter(fn (Croix $c) => $c->getGrimpeur() !== $this->auteur)->toArray());
    }

    /** Une voie est validée dès qu'un autre grimpeur que son auteur l'a réussie. */
    public function estValidee(): bool
    {
        return [] !== $this->getCroixValidantes();
    }

    /** Pose (ou met à jour) la croix et recalcule la cotation affichée. */
    public function ajouterCroix(Croix $croix): static
    {
        if (!$this->croix->contains($croix)) {
            $this->croix->add($croix);
        }
        $this->recalculerCotation();

        return $this;
    }

    public function retirerCroix(Croix $croix): static
    {
        $this->croix->removeElement($croix);
        $this->recalculerCotation();

        return $this;
    }

    public function getCroixDe(?User $user): ?Croix
    {
        foreach ($this->croix as $c) {
            if ($c->getGrimpeur() === $user) {
                return $c;
            }
        }

        return null;
    }

    /** @return list<array{x: float, y: float, r: float, type: string}> */
    public function getPrisesArray(): array
    {
        return array_values($this->prises->map(fn (Prise $p) => $p->toArray())->toArray());
    }
}
