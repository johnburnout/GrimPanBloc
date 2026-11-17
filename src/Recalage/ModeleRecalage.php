<?php

namespace App\Recalage;

/**
 * Passage d'une photo à une autre : la matrice (homographie) donne le mouvement
 * d'ensemble, et une correction locale tient compte des prises que les grimpeurs
 * ont replacées à la main (mur non plan, objectif grand-angle…).
 *
 * La correction est l'écart entre la position choisie et celle que donne la
 * matrice, pour chaque paire connue, étendu autour d'elle avec un poids gaussien :
 * entière sur la prise elle-même, nulle loin des prises connues.
 */
final class ModeleRecalage
{
    /** Portée de la correction locale, en % de la photo. */
    public const PORTEE = 6.0;

    /** @var list<array{float, float, float, float}> [x, y, écart x, écart y] */
    private array $ecarts = [];

    /**
     * @param list<float> $matrice
     * @param list<array{0: float, 1: float, 2: float, 3: float}> $paires
     */
    public function __construct(private readonly array $matrice, array $paires)
    {
        foreach ($paires as [$xs, $ys, $xc, $yc]) {
            [$x, $y] = Homographie::appliquer($matrice, $xs, $ys);
            $this->ecarts[] = [$xs, $ys, $xc - $x, $yc - $y];
        }
    }

    /** @return array{float, float} position sur la nouvelle photo, en % */
    public function transformer(float $x, float $y): array
    {
        [$u, $v] = Homographie::appliquer($this->matrice, $x, $y);
        $somme = $dx = $dy = 0.0;
        foreach ($this->ecarts as [$xs, $ys, $ex, $ey]) {
            $w = exp(-(($x - $xs) ** 2 + ($y - $ys) ** 2) / self::PORTEE ** 2);
            $somme += $w;
            $dx += $w * $ex;
            $dy += $w * $ey;
        }
        // Plusieurs paires proches : moyenne de leurs écarts ; une seule : son écart, atténué avec la distance
        $diviseur = max(1.0, $somme);

        return [$u + $dx / $diviseur, $v + $dy / $diviseur];
    }
}
