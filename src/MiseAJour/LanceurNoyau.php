<?php

namespace App\MiseAJour;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Lance la commande dans le noyau de la requête en cours. Utilisé par la
 * finalisation web, qui tourne déjà avec le NOUVEAU code.
 */
#[AsAlias(LanceurCommandes::class)]
final class LanceurNoyau implements LanceurCommandes
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public function lancer(array $arguments): array
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $sortie = new BufferedOutput();
        $code = $application->run(new ArrayInput($arguments + ['--no-interaction' => true]), $sortie);

        return ['code' => $code, 'sortie' => trim($sortie->fetch())];
    }
}
