<?php

namespace App\MiseAJour;

/** Lance une commande Symfony pendant la finalisation d'une mise à jour. */
interface LanceurCommandes
{
    /**
     * @param array<string, mixed> $arguments au format d'ArrayInput : ['command' => 'nom', '--option' => true]
     *
     * @return array{code: int, sortie: string}
     */
    public function lancer(array $arguments): array;
}
