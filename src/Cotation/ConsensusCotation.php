<?php

namespace App\Cotation;

/**
 * Choisit la cotation affichée d'une voie à partir des propositions.
 *
 * - Tant qu'aucun validateur (grimpeur autre que l'auteur ayant réussi la voie)
 *   n'a proposé de cotation, c'est la proposition de l'auteur qui s'affiche.
 * - Ensuite, seules les propositions des validateurs comptent : la cotation
 *   affichée est la plus proposée.
 * - En cas d'égalité, la proposition de l'auteur départage si elle fait partie
 *   des cotations à égalité ; sinon, on prend la médiane des cotations à égalité
 *   (classées de la plus facile à la plus dure ; avec un nombre pair, la plus
 *   facile des deux du milieu).
 */
final class ConsensusCotation
{
    /**
     * @param list<string> $votes cotations proposées par les validateurs
     * @param string $auteur cotation proposée par l'auteur
     * @param callable(string): int $rang rang de difficulté d'une cotation
     */
    public static function calculer(array $votes, string $auteur, callable $rang): string
    {
        if ([] === $votes) {
            return $auteur;
        }

        $comptes = array_count_values($votes);
        $max = max($comptes);
        $egalite = array_map('strval', array_keys(array_filter($comptes, fn (int $n) => $n === $max)));

        if (1 === \count($egalite)) {
            return $egalite[0];
        }
        if (\in_array($auteur, $egalite, true)) {
            return $auteur;
        }
        usort($egalite, fn (string $a, string $b) => $rang($a) <=> $rang($b));

        return $egalite[intdiv(\count($egalite) - 1, 2)];
    }

    /**
     * Nombre de propositions par cotation, de la plus facile à la plus dure.
     *
     * @param list<string> $votes
     *
     * @return array<string, int>
     */
    public static function repartition(array $votes, callable $rang): array
    {
        $comptes = array_count_values($votes);
        uksort($comptes, fn ($a, $b) => $rang((string) $a) <=> $rang((string) $b));

        return $comptes;
    }
}
