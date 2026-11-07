<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\Voie;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur une voie :
 * - MODIFIER : l'auteur peut toujours corriger le nom et la description ;
 * - MODIFIER_TRACE : photo, prises et cotation, seulement tant que la voie
 *   n'a été validée (réussie) par aucun autre grimpeur ;
 * - SUPPRIMER : l'auteur ou un administrateur (une voie validée demande en plus
 *   le mot de confirmation, voir ConfirmationSuppression).
 *
 * @extends Voter<string, Voie>
 */
class VoieVoter extends Voter
{
    public const MODIFIER = 'VOIE_MODIFIER';
    public const MODIFIER_TRACE = 'VOIE_MODIFIER_TRACE';
    public const SUPPRIMER = 'VOIE_SUPPRIMER';

    public function __construct(private readonly AccessDecisionManagerInterface $decisions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::MODIFIER, self::MODIFIER_TRACE, self::SUPPRIMER], true) && $subject instanceof Voie;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $estAuteur = $subject->getAuteur() === $user;

        return match ($attribute) {
            self::MODIFIER => $estAuteur,
            self::MODIFIER_TRACE => $estAuteur && !$subject->estValidee(),
            self::SUPPRIMER => $estAuteur || $this->decisions->decide($token, ['ROLE_ADMIN']),
        };
    }
}
