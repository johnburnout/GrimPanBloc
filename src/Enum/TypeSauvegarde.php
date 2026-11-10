<?php

namespace App\Enum;

/** Origine d'une sauvegarde : seules les automatiques sont purgées automatiquement. */
enum TypeSauvegarde: string
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
}
