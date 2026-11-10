<?php

namespace App\Command;

use App\Sauvegarde\GestionSauvegardes;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sauvegarde des secteurs, voies et photos en ligne de commande (cron, SSH).
 *
 *   php bin/console app:sauvegarde                 # sauvegarde manuelle (jamais purgée d'office)
 *   php bin/console app:sauvegarde --automatique   # automatique : seules les N dernières sont gardées
 */
#[AsCommand(name: 'app:sauvegarde', description: 'Sauvegarde les secteurs, voies et photos dans var/sauvegardes/')]
class SauvegardeCommand
{
    public function __construct(private readonly GestionSauvegardes $sauvegardes)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Sauvegarde automatique (purgée avec les plus anciennes)')] bool $automatique = false,
        #[Option(description: 'Motif enregistré avec la sauvegarde')] string $motif = '',
    ): int {
        try {
            $s = $automatique
                ? $this->sauvegardes->creerAutomatique('' !== $motif ? $motif : 'ligne de commande')
                : $this->sauvegardes->creerManuelle($motif);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $io->success(\sprintf('Sauvegarde créée : %s (%s Mo).', $s->chemin, number_format($s->taille / 1048576, 1, ',', ' ')));

        return Command::SUCCESS;
    }
}
