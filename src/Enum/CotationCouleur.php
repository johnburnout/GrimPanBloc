<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Échelle de couleurs de difficulté croissante.
 */
enum CotationCouleur: string implements TranslatableInterface
{
    case Vert = 'vert';
    case Jaune = 'jaune';
    case Bleu = 'bleu';
    case Orange = 'orange';
    case Rouge = 'rouge';
    case Noir = 'noir';
    case Blanc = 'blanc';

    public function rang(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    public function libelle(): string
    {
        return ucfirst($this->value);
    }

    public function couleurBadge(): string
    {
        return match ($this) {
            self::Vert => '#22a35a',
            self::Jaune => '#f2c500',
            self::Bleu => '#2f6fdb',
            self::Orange => '#e8771e',
            self::Rouge => '#d63030',
            self::Noir => '#1d1d1d',
            self::Blanc => '#ffffff',
        };
    }

    /** Nom de la couleur dans la langue de l'interface. */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.couleur.'.$this->value, locale: $locale);
    }
}
