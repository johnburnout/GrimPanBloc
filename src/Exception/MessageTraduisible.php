<?php

namespace App\Exception;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Exceptions dont le message est une clé de traduction (domaine « messages ») :
 * le texte est produit dans la langue de la personne qui le lit.
 */
trait MessageTraduisible
{
    /** @var array<string, mixed> */
    private array $parametres = [];

    /** @param array<string, mixed> $parametres */
    public function __construct(string $cle, array $parametres = [], ?\Throwable $precedente = null)
    {
        parent::__construct($cle, 0, $precedente);
        $this->parametres = $parametres;
    }

    /** @return array<string, mixed> */
    public function getParametres(): array
    {
        return $this->parametres;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getMessage(), array_map(
            static fn ($p) => $p instanceof TranslatableInterface ? $p->trans($translator, $locale) : $p,
            $this->parametres,
        ), null, $locale);
    }
}
