<?php

namespace App\Locale;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Langues de l'interface. Celle d'une requête est, dans l'ordre : celle choisie
 * dans le sélecteur (cookie, recopié à la connexion depuis le compte), celle
 * préférée par le navigateur, sinon le français.
 */
final class Langues
{
    public const COOKIE = 'langue';
    public const DEFAUT = 'fr';

    /** Nom de chaque langue dans cette langue même, pour le sélecteur. */
    public const NOMS = ['fr' => 'Français', 'en' => 'English', 'es' => 'Español', 'it' => 'Italiano', 'ru' => 'Русский'];

    /** @param list<string> $actives */
    public function __construct(
        #[Autowire('%kernel.enabled_locales%')] private readonly array $actives,
    ) {
    }

    /** @return array<string, string> code → nom, pour les langues activées */
    public function toutes(): array
    {
        return array_intersect_key(self::NOMS, array_flip($this->actives));
    }

    public function estActive(?string $langue): bool
    {
        return null !== $langue && \in_array($langue, $this->actives, true);
    }

    public function pour(Request $request): string
    {
        $choisie = $request->cookies->get(self::COOKIE);
        if ($this->estActive($choisie)) {
            return $choisie;
        }
        // Accept-Language ; sans correspondance, Symfony renvoie la première (le français)
        $preferee = $request->getPreferredLanguage($this->actives);

        return $this->estActive($preferee) ? $preferee : self::DEFAUT;
    }
}
