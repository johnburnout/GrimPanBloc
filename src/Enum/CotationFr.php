<?php

namespace App\Enum;

/**
 * Cotation sur l'échelle française, de 3a à 9c.
 * L'ordre des cas définit la difficulté (voir rang()).
 */
enum CotationFr: string
{
    case C3a = '3a'; case C3b = '3b'; case C3c = '3c';
    case C4a = '4a'; case C4b = '4b'; case C4c = '4c';
    case C5a = '5a'; case C5b = '5b'; case C5c = '5c';
    case C6a = '6a'; case C6b = '6b'; case C6c = '6c';
    case C7a = '7a'; case C7b = '7b'; case C7c = '7c';
    case C8a = '8a'; case C8b = '8b'; case C8c = '8c';
    case C9a = '9a'; case C9b = '9b'; case C9c = '9c';

    public function rang(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    public function libelle(): string
    {
        return $this->value;
    }

    /** Couleur d'affichage du badge, du vert (facile) au noir (dur). */
    public function couleurBadge(): string
    {
        return match ((int) $this->value[0]) {
            3, 4 => '#22a35a',
            5 => '#e0b400',
            6 => '#2f6fdb',
            7 => '#e8771e',
            8 => '#d63030',
            default => '#1d1d1d',
        };
    }
}
