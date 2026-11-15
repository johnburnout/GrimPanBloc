<?php

namespace App\Site;

use App\Repository\ReglageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Informations pratiques sur la salle (adresse, localisation, site internet,
 * horaires, hauteur et surface grimpable), saisies dans Administration → Réglages
 * et affichées sur la page « Informations ».
 *
 * Stockées dans la table « reglage » (clés « salle.* »). Lues seulement par ces
 * deux pages : pas de cache.
 */
final class InformationsSalle
{
    public const JOURS = ['lundi' => 'Lundi', 'mardi' => 'Mardi', 'mercredi' => 'Mercredi', 'jeudi' => 'Jeudi',
        'vendredi' => 'Vendredi', 'samedi' => 'Samedi', 'dimanche' => 'Dimanche'];

    public const LONGUEUR_MAX_ADRESSE = 300;
    public const LONGUEUR_MAX_HORAIRE = 100;
    public const LONGUEUR_MAX_REMARQUE = 300;

    public function __construct(
        private readonly ReglageRepository $reglages,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Valeurs enregistrées, au format du formulaire SalleType.
     *
     * @return array{adresse: string, coordonnees: string, site_web: string, horaires_affiches: bool,
     *     horaires: array<string, string>, horaires_remarque: string, hauteur: ?float, surface: ?int}
     */
    public function lire(): array
    {
        $horaires = json_decode((string) $this->reglages->valeur('salle.horaires', ''), true);
        $horaires = \is_array($horaires) ? $horaires : [];
        $hauteur = $this->reglages->valeur('salle.hauteur', '');
        $surface = $this->reglages->valeur('salle.surface', '');
        $position = $this->position();

        return [
            'adresse' => (string) $this->reglages->valeur('salle.adresse', ''),
            'coordonnees' => $position ? self::formaterCoordonnees(...$position) : '',
            'site_web' => (string) $this->reglages->valeur('salle.site_web', ''),
            'horaires_affiches' => '1' === $this->reglages->valeur('salle.horaires_affiches'),
            'horaires' => array_map(static fn (string $jour): string => \is_string($horaires[$jour] ?? null) ? $horaires[$jour] : '',
                array_combine(array_keys(self::JOURS), array_keys(self::JOURS))),
            'horaires_remarque' => (string) $this->reglages->valeur('salle.horaires_remarque', ''),
            'hauteur' => is_numeric($hauteur) ? (float) $hauteur : null,
            'surface' => is_numeric($surface) ? (int) $surface : null,
        ];
    }

    /** @return array{0: float, 1: float}|null latitude et longitude enregistrées */
    public function position(): ?array
    {
        return self::analyserCoordonnees((string) $this->reglages->valeur('salle.coordonnees', ''));
    }

    /** Vrai si au moins une information est renseignée (horaires : seulement s'ils sont affichés). */
    public function estRenseigne(): bool
    {
        $infos = $this->lire();

        return '' !== $infos['adresse'] || '' !== $infos['coordonnees'] || '' !== $infos['site_web']
            || null !== $infos['hauteur'] || null !== $infos['surface']
            || ($infos['horaires_affiches'] && [] !== array_filter($infos['horaires']));
    }

    /**
     * @param array{adresse: ?string, coordonnees: ?string, site_web: ?string, horaires_affiches: bool,
     *     horaires: array<string, ?string>, horaires_remarque: ?string, hauteur: int|float|null, surface: ?int} $infos
     */
    public function enregistrer(array $infos): void
    {
        $position = self::analyserCoordonnees((string) $infos['coordonnees']);
        $horaires = [];
        foreach (array_keys(self::JOURS) as $jour) {
            $horaires[$jour] = trim((string) ($infos['horaires'][$jour] ?? ''));
        }

        $this->reglages->definir('salle.adresse', trim((string) $infos['adresse']));
        $this->reglages->definir('salle.coordonnees', $position ? self::formaterCoordonnees(...$position) : '');
        $this->reglages->definir('salle.site_web', trim((string) $infos['site_web']));
        $this->reglages->definir('salle.horaires_affiches', $infos['horaires_affiches'] ? '1' : '0');
        $this->reglages->definir('salle.horaires', json_encode($horaires, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->reglages->definir('salle.horaires_remarque', trim((string) $infos['horaires_remarque']));
        $this->reglages->definir('salle.hauteur', null === $infos['hauteur'] ? '' : (string) round((float) $infos['hauteur'], 1));
        $this->reglages->definir('salle.surface', null === $infos['surface'] ? '' : (string) (int) $infos['surface']);
        $this->em->flush();
    }

    /**
     * Lit « latitude, longitude » tel que copié depuis une carte en ligne :
     * « 44.8312, 1.3254 », « 44,8312 1,3254 », « 44.8312;1.3254 »…
     *
     * @return array{0: float, 1: float}|null null si vide ou invalide
     */
    public static function analyserCoordonnees(string $saisie): ?array
    {
        $nombre = '(-?\d{1,3}(?:[.,]\d+)?)';
        if (!preg_match('/^\s*'.$nombre.'\s*(?:;|,|\s)\s*'.$nombre.'\s*$/', $saisie, $m)) {
            return null;
        }
        $latitude = (float) str_replace(',', '.', $m[1]);
        $longitude = (float) str_replace(',', '.', $m[2]);

        return abs($latitude) <= 90 && abs($longitude) <= 180 ? [$latitude, $longitude] : null;
    }

    public static function formaterCoordonnees(float $latitude, float $longitude): string
    {
        return \sprintf('%s, %s', round($latitude, 6), round($longitude, 6));
    }
}
