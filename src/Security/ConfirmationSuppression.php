<?php

namespace App\Security;

/**
 * Garde-fou des suppressions définitives (voie validée, compte utilisateur) :
 * l'utilisateur doit taper ce mot dans le champ « confirmation ».
 */
final class ConfirmationSuppression
{
    public const MOT = 'SUPPRIMER';
    public const MESSAGE_ANNULATION = 'Suppression annulée : tapez '.self::MOT.' pour confirmer.';

    public static function estConfirmee(string $saisie): bool
    {
        return self::MOT === trim($saisie);
    }
}
