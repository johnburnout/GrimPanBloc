<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Refuse la connexion d'un compte désactivé par un administrateur. */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isActif()) {
            throw new CustomUserMessageAccountStatusException('Ce compte a été désactivé. Contactez un administrateur.');
        }
    }

    public function checkPostAuth(UserInterface $user, mixed $token = null): void
    {
    }
}
