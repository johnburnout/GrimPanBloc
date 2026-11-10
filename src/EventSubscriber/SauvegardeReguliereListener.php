<?php

namespace App\EventSubscriber;

use App\Sauvegarde\GestionSauvegardes;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sauvegarde régulière sans cron (hébergement mutualisé) : après la réponse à une
 * requête d'administrateur, si la dernière sauvegarde est trop ancienne. Sur un
 * serveur avec cron, « php bin/console app:sauvegarde --automatique » fait la même chose.
 */
#[AsEventListener(KernelEvents::TERMINATE)]
final class SauvegardeReguliereListener
{
    public function __construct(
        private readonly GestionSauvegardes $sauvegardes,
        private readonly Security $security,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        if ($event->getResponse()->isSuccessful() && $this->security->isGranted('ROLE_ADMIN')) {
            $this->sauvegardes->sauvegardeReguliereSiNecessaire();
        }
    }
}
