<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum TypePrise: string implements TranslatableInterface
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

    /** Libellé dans la langue de l'interface (libelle() : en français). */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.type_prise.'.$this->value, locale: $locale);
    }
}
