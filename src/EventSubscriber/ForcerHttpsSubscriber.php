<?php

namespace App\EventSubscriber;

use App\Repository\ReglageRepository;
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
 */
final class ForcerHttpsSubscriber implements EventSubscriberInterface
{
    public const CLE = 'site.forcer_https';

    private ?bool $actif = null;

    public function __construct(private readonly ReglageRepository $reglages)
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
        if (!$event->isMainRequest() || $request->isSecure() || !$this->actif()) {
            return;
        }
        // 301 pour une page, 308 pour un formulaire (conserve la méthode POST)
        $code = \in_array($request->getMethod(), ['GET', 'HEAD'], true) ? 301 : 308;
        $event->setResponse(new RedirectResponse('https://'.$request->getHttpHost().$request->getRequestUri(), $code));
    }

    public function entetes(ResponseEvent $event): void
    {
        if ($event->isMainRequest() && $event->getRequest()->isSecure() && $this->actif()) {
            // Le navigateur passe lui-même en HTTPS pendant 30 jours (durée volontairement courte).
            $event->getResponse()->headers->set('Strict-Transport-Security', 'max-age=2592000');
        }
    }

    private function actif(): bool
    {
        if (null === $this->actif) {
            try {
                $this->actif = '1' === $this->reglages->valeur(self::CLE);
            } catch (\Throwable) {
                $this->actif = false; // base pas encore installée ou pas encore migrée
            }
        }

        return $this->actif;
    }
}
