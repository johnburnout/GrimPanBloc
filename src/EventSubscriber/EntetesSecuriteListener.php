<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * En-têtes de sécurité HTTP ajoutés à toutes les réponses de l'application.
 *
 * Un en-tête déjà posé par un contrôleur n'est pas écrasé. Les fichiers servis
 * directement par le serveur (photos de uploads/) ne passent pas par ici : voir
 * public/uploads/.htaccess. HSTS reste géré par ForcerHttpsSubscriber.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class EntetesSecuriteListener
{
    public const ENTETES = [
        // Le navigateur respecte le Content-Type annoncé au lieu de le deviner
        'X-Content-Type-Options' => 'nosniff',
        // Le site ne peut pas être affiché dans un cadre (clickjacking) :
        // X-Frame-Options pour les vieux navigateurs, frame-ancestors pour les autres
        'X-Frame-Options' => 'DENY',
        'Content-Security-Policy' => "frame-ancestors 'none'",
        // Les autres sites ne reçoivent que l'origine, jamais le chemin ni les paramètres
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $entetes = $event->getResponse()->headers;
        foreach (self::ENTETES as $nom => $valeur) {
            if (!$entetes->has($nom)) {
                $entetes->set($nom, $valeur);
            }
        }
    }
}
