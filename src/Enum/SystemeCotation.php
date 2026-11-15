<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum SystemeCotation: string implements TranslatableInterface
{
    case Francaise = 'fr';
    case Couleur = 'couleur';

    /** Cotation de cette échelle correspondant à la valeur brute (« 6a », « bleu »), ou null. */
    public function cotation(?string $valeur): CotationFr|CotationCouleur|null
    {
        if (null === $valeur) {
            return null;
        }

        return match ($this) {
            self::Francaise => CotationFr::tryFrom($valeur),
            self::Couleur => CotationCouleur::tryFrom($valeur),
        };
    }

    /** @return list<CotationFr>|list<CotationCouleur> */
    public function cotations(): array
    {
        return match ($this) {
            self::Francaise => CotationFr::cases(),
            self::Couleur => CotationCouleur::cases(),
        };
    }

    /** Rang de difficulté d'une valeur brute (0 si inconnue). */
    public function rang(string $valeur): int
    {
        return $this->cotation($valeur)?->rang() ?? 0;
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Francaise => 'Échelle française',
            self::Couleur => 'Couleurs',
        };
    }

    /** Libellé dans la langue de l'interface (libelle() : en français). */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.systeme_cotation.'.$this->value, locale: $locale);
    }
}
