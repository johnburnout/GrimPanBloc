<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Garde-fou des actions définitives (suppression d'une voie validée, d'un compte,
 * d'un secteur ; restauration) : l'utilisateur doit taper un mot dans le champ
 * « confirmation » (« SUPPRIMER », « DELETE »…).
 *
 * Le mot est accepté dans toutes les langues de l'interface : la langue peut
 * changer entre l'affichage du formulaire et son envoi.
 */
final class ConfirmationSuppression
{
    public const SUPPRIMER = 'confirmation.supprimer';
    public const RESTAURER = 'confirmation.restaurer';

    /** @param list<string> $langues */
    public function __construct(
        private readonly TranslatorInterface $translator,
        #[Autowire('%kernel.enabled_locales%')] private readonly array $langues,
    ) {
    }

    public function estConfirmee(string $saisie, string $mot = self::SUPPRIMER): bool
    {
        $saisie = trim($saisie);
        foreach ($this->langues as $langue) {
            if ($saisie === $this->translator->trans($mot, [], null, $langue)) {
                return true;
            }
        }

        return false;
    }

    /** « Suppression annulée : tapez SUPPRIMER pour confirmer. » (ou nettoyage, restauration…) */
    public static function annulation(string $action = 'suppression', string $mot = self::SUPPRIMER): TranslatableMessage
    {
        return new TranslatableMessage('confirmation.annulee.'.$action, ['mot' => new TranslatableMessage($mot)]);
    }
}
