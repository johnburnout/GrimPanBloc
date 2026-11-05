<?php

namespace App\Command;

use App\MiseAJour\InstallateurMiseAJour;
use App\MiseAJour\VerificateurMiseAJour;
use App\MiseAJour\VersionInstallee;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Mise à jour en SSH (même mécanisme que la page d'administration).
 *
 *   php bin/console app:mise-a-jour --verifier   # vérifie seulement
 *   php bin/console app:mise-a-jour              # installe la dernière release
 */
#[AsCommand(name: 'app:mise-a-jour', description: 'Vérifie et installe la dernière release GitHub de GrimPanBloc')]
class MiseAJourCommand
{
    public function __construct(
        private readonly VerificateurMiseAJour $verificateur,
        private readonly InstallateurMiseAJour $installateur,
        private readonly VersionInstallee $version,
        private readonly string $racine,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Indique seulement si une mise à jour est disponible')] bool $verifier = false,
    ): int {
        $io->text('Version installée : <info>'.$this->version->get().'</info>');
        $release = $this->verificateur->derniereRelease(true);
        if (!$release) {
            $io->warning('Impossible de connaître la dernière release (aucune release, ou GitHub injoignable).');

            return Command::FAILURE;
        }
        if (!$this->version->estDepasseePar($release->version)) {
            $io->success("GrimPanBloc est à jour (dernière release : {$release->version}).");

            return Command::SUCCESS;
        }

        $io->text("Nouvelle version disponible : <comment>{$release->version}</comment>  {$release->url}");
        if ($release->notes) {
            $io->block($release->notes, 'NOUVEAUTÉS');
        }
        if ($verifier) {
            return Command::SUCCESS;
        }
        if (!$io->confirm("Installer la version {$release->version} ? Pensez à sauvegarder la base de données avant.", true)) {
            return Command::SUCCESS;
        }

        try {
            $this->installateur->installer($release, fn (string $ligne) => $io->text('✔ '.$ligne));
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        // Finalisation avec le NOUVEAU code : nouveaux processus PHP
        $console = function (array $arguments): array {
            $commande = [\PHP_BINARY, $this->racine.'/bin/console', $arguments['command'], '--no-interaction', '--env=prod'];
            foreach ($arguments as $cle => $valeur) {
                if ('command' !== $cle && true === $valeur) {
                    $commande[] = $cle;
                }
            }
            $process = new Process($commande, $this->racine, null, null, 600);
            $process->run();

            return ['code' => (int) $process->getExitCode(), 'sortie' => trim($process->getOutput()."\n".$process->getErrorOutput())];
        };
        try {
            foreach ($this->installateur->finaliser($console) as $avertissement) {
                $io->warning($avertissement);
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $io->success("GrimPanBloc est passé à la version {$release->version}.");
        $io->note('Si OPcache est configuré avec validate_timestamps = 0, rechargez PHP-FPM : sudo systemctl reload php8.4-fpm');

        return Command::SUCCESS;
    }
}
