<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Origine d'une sauvegarde : seules les automatiques sont purgées automatiquement. */
enum TypeSauvegarde: string implements TranslatableInterface
{
    case Manuelle = 'manuelle';
    case Automatique = 'automatique';
    case Importee = 'importee';

    public function libelle(): string
    {
        return match ($this) {
            self::Manuelle => 'Manuelle',
            self::Automatique => 'Automatique',
            self::Importee => 'Importée',
        };
    }

    /** Libellé dans la langue de l'interface (libelle() : en français). */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.type_sauvegarde.'.$this->value, locale: $locale);
    }
}
