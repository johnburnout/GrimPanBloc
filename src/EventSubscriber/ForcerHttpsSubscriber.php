<?php

namespace App\EventSubscriber;

use App\Site\ReglageHttps;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Réglage « Toujours utiliser HTTPS » (Administration → Réglages) : redirige
 * toute requête HTTP vers son équivalent HTTPS.
 *
 * Le réglage ne peut être activé que depuis une page ouverte en HTTPS : cela
 * prouve que PHP détecte bien le HTTPS, sinon la redirection tournerait en boucle.
 * Sa valeur est lue dans le cache de l'application (voir ReglageHttps).
 */
final class ForcerHttpsSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ReglageHttps $reglage)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Avant le pare-feu (priorité 8) : la redirection ne dépend pas de l'utilisateur
            KernelEvents::REQUEST => ['rediriger', 64],
            KernelEvents::RESPONSE => 'entetes',
        ];
    }

    public function rediriger(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || $request->isSecure() || !$this->reglage->actif()) {
            return;
        }
        // 301 pour une page, 308 pour un formulaire (conserve la méthode POST)
        $code = \in_array($request->getMethod(), ['GET', 'HEAD'], true) ? 301 : 308;
        $event->setResponse(new RedirectResponse('https://'.$request->getHttpHost().$request->getRequestUri(), $code));
    }

    public function entetes(ResponseEvent $event): void
    {
        if ($event->isMainRequest() && $event->getRequest()->isSecure() && $this->reglage->actif()) {
            // Le navigateur passe lui-même en HTTPS pendant 30 jours (durée volontairement courte).
            $event->getResponse()->headers->set('Strict-Transport-Security', 'max-age=2592000');
        }
    }
}
