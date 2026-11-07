<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;

/**
 * Un jeton CSRF invalide (attribut #[IsCsrfTokenValid]) est un refus d'accès,
 * pas un problème de connexion : sans cette conversion, le pare-feu renverrait
 * un utilisateur connecté vers la page de connexion au lieu d'une erreur 403.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)] // avant le pare-feu (priorité 1)
final class JetonCsrfInvalideListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception instanceof InvalidCsrfTokenException) {
            $event->setThrowable(new AccessDeniedException('Jeton CSRF invalide.', $exception));
        }
    }
}
