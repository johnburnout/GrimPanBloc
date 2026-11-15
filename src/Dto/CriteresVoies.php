<?php

namespace App\Dto;

use App\Enum\CotationCouleur;
use App\Enum\CotationFr;
use App\Enum\SystemeCotation;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Affichage d'une liste de voies (accueil et page d'un secteur), lu dans l'adresse :
 * filtres (secteur, échelle, plage de difficulté), tri et mode d'affichage.
 *
 * Les filtres restent des chaînes : une valeur vide ou inconnue (vieux lien) est
 * simplement ignorée. Tri, ordre et mode n'admettent que les valeurs prévues.
 */
final readonly class CriteresVoies
{
    public const TRIS = ['nom', 'cotation', 'secteur', 'ouvreur'];
    public const ORDRES = ['asc', 'desc'];
    public const VUES = ['icones', 'liste'];

    public function __construct(
        public string $secteur = '',
        public string $systeme = '',
        public string $min = '',
        public string $max = '',
        #[Assert\Choice(choices: self::TRIS)]
        public string $tri = 'cotation',
        #[Assert\Choice(choices: self::ORDRES)]
        public string $ordre = 'asc',
        #[Assert\Choice(choices: self::VUES)]
        public string $vue = 'icones',
    ) {
    }

    public function secteurId(): ?int
    {
        return ctype_digit($this->secteur) ? (int) $this->secteur : null;
    }

    public function systemeCotation(): ?SystemeCotation
    {
        return SystemeCotation::tryFrom($this->systeme);
    }

    /** Borne basse de difficulté, seulement si une échelle est choisie. */
    public function cotationMin(): CotationFr|CotationCouleur|null
    {
        return $this->systemeCotation()?->cotation($this->min);
    }

    public function cotationMax(): CotationFr|CotationCouleur|null
    {
        return $this->systemeCotation()?->cotation($this->max);
    }

    public function estListe(): bool
    {
        return 'liste' === $this->vue;
    }

    /**
     * Paramètres d'adresse de cet affichage, avec des changements éventuels
     * (pour les liens de tri et de changement de mode). Les valeurs vides sont omises.
     *
     * @param array<string, string> $changements
     *
     * @return array<string, string>
     */
    public function parametres(array $changements = []): array
    {
        $parametres = [
            'secteur' => $this->secteur,
            'systeme' => $this->systeme,
            'min' => $this->min,
            'max' => $this->max,
            'tri' => $this->tri,
            'ordre' => $this->ordre,
            'vue' => $this->vue,
        ];

        return array_filter([...$parametres, ...$changements], fn (string $v) => '' !== $v);
    }

    /**
     * Lien d'en-tête de colonne : un nouveau clic sur le tri en cours inverse l'ordre.
     *
     * @return array<string, string>
     */
    public function parametresTri(string $tri): array
    {
        $ordre = $tri === $this->tri && 'asc' === $this->ordre ? 'desc' : 'asc';

        return $this->parametres(['tri' => $tri, 'ordre' => $ordre]);
    }
}
