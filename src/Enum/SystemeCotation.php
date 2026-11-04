<?php

namespace App\Enum;

enum SystemeCotation: string
{
    case Francaise = 'fr';
    case Couleur = 'couleur';

    public function libelle(): string
    {
        return match ($this) {
            self::Francaise => 'Échelle française',
            self::Couleur => 'Couleurs',
        };
    }
}
