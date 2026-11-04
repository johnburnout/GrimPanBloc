<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\Voie;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits de modification d'une voie :
 * - MODIFIER : l'auteur peut toujours corriger le nom et la description ;
 * - MODIFIER_TRACE : photo, prises et cotation, seulement tant que la voie
 *   n'a été validée (réussie) par aucun autre grimpeur.
 *
 * @extends Voter<string, Voie>
 */
class VoieVoter extends Voter
{
    public const MODIFIER = 'VOIE_MODIFIER';
    public const MODIFIER_TRACE = 'VOIE_MODIFIER_TRACE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::MODIFIER, self::MODIFIER_TRACE], true) && $subject instanceof Voie;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || $subject->getAuteur() !== $user) {
            return false;
        }

        return match ($attribute) {
            self::MODIFIER => true,
            self::MODIFIER_TRACE => !$subject->estValidee(),
        };
    }
}
