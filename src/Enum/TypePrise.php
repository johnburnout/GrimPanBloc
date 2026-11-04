<?php

namespace App\Enum;

enum TypePrise: string
{
    case Depart = 'depart';
    case Main = 'main';
    case Pied = 'pied';
    case Top = 'top';

    public function libelle(): string
    {
        return match ($this) {
            self::Depart => 'Départ',
            self::Main => 'Main',
            self::Pied => 'Pied',
            self::Top => 'Top',
        };
    }
}
