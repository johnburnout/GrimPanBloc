<?php

namespace App\Sauvegarde;

use App\Enum\TypeSauvegarde;

/**
 * Contenu de resume.json : de quoi afficher une sauvegarde (liste, choix du
 * secteur à restaurer) sans lire toutes ses données.
 */
final readonly class ResumeSauvegarde
{
    /**
     * @param list<array{id: int, nom: string, voies: int, photos: int}> $secteurs
     */
    public function __construct(
        public int $format,
        public string $version,
        public TypeSauvegarde $type,
        public string $motif,
        public \DateTimeImmutable $creeLe,
        public array $secteurs,
        public int $photosSansFichier = 0,
    ) {
    }

    public function nombreVoies(): int
    {
        return array_sum(array_column($this->secteurs, 'voies'));
    }

    public function nombrePhotos(): int
    {
        return array_sum(array_column($this->secteurs, 'photos'));
    }

    /** @return array{id: int, nom: string, voies: int, photos: int}|null */
    public function secteur(int $id): ?array
    {
        foreach ($this->secteurs as $s) {
            if ($s['id'] === $id) {
                return $s;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function versTableau(): array
    {
        return [
            'format' => $this->format,
            'application' => 'GrimPanBloc',
            'version' => $this->version,
            'type' => $this->type->value,
            'motif' => $this->motif,
            'creeLe' => $this->creeLe->format(\DATE_ATOM),
            'secteurs' => $this->secteurs,
            'photosSansFichier' => $this->photosSansFichier,
        ];
    }

    /** @throws \InvalidArgumentException si le tableau n'est pas un résumé valide */
    public static function depuisTableau(mixed $d): self
    {
        if (!\is_array($d) || !\is_int($d['format'] ?? null) || !\is_array($d['secteurs'] ?? null)) {
            throw new \InvalidArgumentException('résumé illisible');
        }
        $creeLe = \is_string($d['creeLe'] ?? null) ? \DateTimeImmutable::createFromFormat(\DATE_ATOM, $d['creeLe']) : false;
        $type = \is_string($d['type'] ?? null) ? TypeSauvegarde::tryFrom($d['type']) : null;
        if (false === $creeLe || null === $type) {
            throw new \InvalidArgumentException('résumé illisible');
        }
        $secteurs = [];
        foreach ($d['secteurs'] as $s) {
            if (!\is_array($s) || !\is_int($s['id'] ?? null) || !\is_string($s['nom'] ?? null) || !\is_int($s['voies'] ?? null) || !\is_int($s['photos'] ?? null)) {
                throw new \InvalidArgumentException('résumé illisible');
            }
            $secteurs[] = ['id' => $s['id'], 'nom' => $s['nom'], 'voies' => $s['voies'], 'photos' => $s['photos']];
        }

        return new self(
            $d['format'],
            \is_string($d['version'] ?? null) ? $d['version'] : '?',
            $type,
            \is_string($d['motif'] ?? null) ? $d['motif'] : '',
            $creeLe,
            $secteurs,
            \is_int($d['photosSansFichier'] ?? null) ? $d['photosSansFichier'] : 0,
        );
    }
}
