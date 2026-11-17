<?php

namespace App\Recalage;

/**
 * Matrice 3×3 (homographie) qui envoie les points d'une photo sur une autre photo
 * du même mur, estimée par moindres carrés à partir de paires de points
 * (position d'une prise sur l'ancienne photo, position sur la nouvelle).
 *
 * Les coordonnées sont en % (0-100), comme les prises. Selon le nombre de paires :
 * - 0 : identité ;
 * - 1 : translation ;
 * - 2 : similitude (translation, rotation, échelle) ;
 * - 3 : transformation affine ;
 * - 4 et plus : homographie (perspective : photo prise d'un autre endroit), avec
 *   repli sur l'affine si le résultat est dégénéré (image « retournée »).
 *
 * La matrice est rangée ligne par ligne : [h0 h1 h2 / h3 h4 h5 / h6 h7 h8].
 */
final class Homographie
{
    private const EPSILON = 1e-10;

    /** @return list<float> matrice identité */
    public static function identite(): array
    {
        return [1.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 1.0];
    }

    /**
     * @param list<array{0: float, 1: float, 2: float, 3: float}> $paires [xSource, ySource, xCible, yCible]
     *
     * @return list<float>
     */
    public static function estimer(array $paires): array
    {
        $n = \count($paires);
        if (0 === $n) {
            return self::identite();
        }

        // Normalisation (Hartley) : points centrés, distance moyenne √2, pour un calcul stable
        [$ts, $source] = self::normaliser(array_map(fn (array $p) => [$p[0], $p[1]], $paires));
        [$tc, $cible] = self::normaliser(array_map(fn (array $p) => [$p[2], $p[3]], $paires));
        $normees = [];
        foreach ($source as $i => $s) {
            $normees[] = [$s[0], $s[1], $cible[$i][0], $cible[$i][1]];
        }

        $h = null;
        if ($n >= 4) {
            $h = self::homographie($normees);
            if (null !== $h && !self::conserveOrientation(self::denormaliser($h, $ts, $tc), $paires)) {
                $h = null;
            }
        }
        $h ??= $n >= 3 ? self::affine($normees) : null;
        $h ??= $n >= 2 ? self::similitude($normees) : null;
        $h ??= self::translation($normees);

        return self::denormaliser($h, $ts, $tc);
    }

    /**
     * @param list<float> $h
     *
     * @return array{float, float}
     */
    public static function appliquer(array $h, float $x, float $y): array
    {
        $w = $h[6] * $x + $h[7] * $y + $h[8];
        if (abs($w) < self::EPSILON) {
            return [$x, $y];
        }

        return [($h[0] * $x + $h[1] * $y + $h[2]) / $w, ($h[3] * $x + $h[4] * $y + $h[5]) / $w];
    }

    // --- Modèles ------------------------------------------------------------

    /**
     * u = (h0 x + h1 y + h2) / (h6 x + h7 y + 1), idem pour v : 8 inconnues.
     *
     * @param list<array{0: float, 1: float, 2: float, 3: float}> $p
     *
     * @return list<float>|null
     */
    private static function homographie(array $p): ?array
    {
        $a = $b = [];
        foreach ($p as [$x, $y, $u, $v]) {
            $a[] = [$x, $y, 1, 0, 0, 0, -$u * $x, -$u * $y];
            $b[] = $u;
            $a[] = [0, 0, 0, $x, $y, 1, -$v * $x, -$v * $y];
            $b[] = $v;
        }
        $h = self::moindresCarres($a, $b);

        return null === $h ? null : [...$h, 1.0];
    }

    /**
     * @param list<array{0: float, 1: float, 2: float, 3: float}> $p
     *
     * @return list<float>|null
     */
    private static function affine(array $p): ?array
    {
        $a = $bu = $bv = [];
        foreach ($p as [$x, $y, $u, $v]) {
            $a[] = [$x, $y, 1];
            $bu[] = $u;
            $bv[] = $v;
        }
        $lu = self::moindresCarres($a, $bu);
        $lv = self::moindresCarres($a, $bv);

        return null === $lu || null === $lv ? null : [...$lu, ...$lv, 0.0, 0.0, 1.0];
    }

    /**
     * u = a x − b y + c, v = b x + a y + d.
     *
     * @param list<array{0: float, 1: float, 2: float, 3: float}> $p
     *
     * @return list<float>|null
     */
    private static function similitude(array $p): ?array
    {
        $m = $b = [];
        foreach ($p as [$x, $y, $u, $v]) {
            $m[] = [$x, -$y, 1, 0];
            $b[] = $u;
            $m[] = [$y, $x, 0, 1];
            $b[] = $v;
        }
        $s = self::moindresCarres($m, $b);
        if (null === $s) {
            return null;
        }
        [$a, $bb, $c, $d] = $s;

        return [$a, -$bb, $c, $bb, $a, $d, 0.0, 0.0, 1.0];
    }

    /**
     * @param list<array{0: float, 1: float, 2: float, 3: float}> $p
     *
     * @return list<float>
     */
    private static function translation(array $p): array
    {
        $dx = $dy = 0.0;
        foreach ($p as [$x, $y, $u, $v]) {
            $dx += $u - $x;
            $dy += $v - $y;
        }
        $n = \count($p);

        return [1.0, 0.0, $dx / $n, 0.0, 1.0, $dy / $n, 0.0, 0.0, 1.0];
    }

    // --- Outils -------------------------------------------------------------

    /**
     * Une homographie à 4 paires peut « retourner » l'image : on vérifie que le
     * dénominateur garde le même signe sur les coins de la photo et les paires.
     *
     * @param list<float> $h
     * @param list<array{0: float, 1: float, 2: float, 3: float}> $paires
     */
    private static function conserveOrientation(array $h, array $paires): bool
    {
        $points = [[0, 0], [100, 0], [0, 100], [100, 100], ...array_map(fn (array $p) => [$p[0], $p[1]], $paires)];
        $signe = null;
        foreach ($points as [$x, $y]) {
            $w = $h[6] * $x + $h[7] * $y + $h[8];
            if (abs($w) < self::EPSILON) {
                return false;
            }
            $signe ??= $w > 0;
            if ($signe !== ($w > 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{float, float}> $points
     *
     * @return array{array{float, float, float}, list<array{float, float}>} [centre x, centre y, échelle], points normalisés
     */
    private static function normaliser(array $points): array
    {
        $n = \count($points);
        $cx = array_sum(array_column($points, 0)) / $n;
        $cy = array_sum(array_column($points, 1)) / $n;
        $d = 0.0;
        foreach ($points as [$x, $y]) {
            $d += hypot($x - $cx, $y - $cy);
        }
        $d /= $n;
        $s = $d > self::EPSILON ? M_SQRT2 / $d : 1.0;

        return [[$cx, $cy, $s], array_map(fn (array $p) => [($p[0] - $cx) * $s, ($p[1] - $cy) * $s], $points)];
    }

    /**
     * H = Tc⁻¹ · Hn · Ts, puis mise à l'échelle pour que h8 vaille 1.
     *
     * @param list<float> $hn
     * @param array{float, float, float} $ts
     * @param array{float, float, float} $tc
     *
     * @return list<float>
     */
    private static function denormaliser(array $hn, array $ts, array $tc): array
    {
        [$sx, $sy, $ss] = $ts;
        [$cx, $cy, $sc] = $tc;
        $mTs = [$ss, 0, -$ss * $sx, 0, $ss, -$ss * $sy, 0, 0, 1];
        $mTcInv = [1 / $sc, 0, $cx, 0, 1 / $sc, $cy, 0, 0, 1];
        $h = self::multiplier($mTcInv, self::multiplier($hn, $mTs));

        if (abs($h[8]) > self::EPSILON) {
            $h = array_map(fn (float $v) => $v / $h[8], $h);
        }

        return array_map(fn ($v) => (float) $v, $h);
    }

    /**
     * @param list<float|int> $a
     * @param list<float|int> $b
     *
     * @return list<float>
     */
    private static function multiplier(array $a, array $b): array
    {
        $r = [];
        for ($i = 0; $i < 3; ++$i) {
            for ($j = 0; $j < 3; ++$j) {
                $r[] = (float) ($a[$i * 3] * $b[$j] + $a[$i * 3 + 1] * $b[3 + $j] + $a[$i * 3 + 2] * $b[6 + $j]);
            }
        }

        return $r;
    }

    /**
     * Résout Aᵀ A x = Aᵀ b (élimination de Gauss avec pivot partiel).
     *
     * @param list<list<float|int>> $a
     * @param list<float|int> $b
     *
     * @return list<float>|null null si le système est singulier (points alignés, confondus…)
     */
    private static function moindresCarres(array $a, array $b): ?array
    {
        $k = \count($a[0]);
        $m = array_fill(0, $k, array_fill(0, $k + 1, 0.0));
        foreach ($a as $l => $ligne) {
            for ($i = 0; $i < $k; ++$i) {
                for ($j = 0; $j < $k; ++$j) {
                    $m[$i][$j] += $ligne[$i] * $ligne[$j];
                }
                $m[$i][$k] += $ligne[$i] * $b[$l];
            }
        }

        for ($c = 0; $c < $k; ++$c) {
            $pivot = $c;
            for ($i = $c + 1; $i < $k; ++$i) {
                if (abs($m[$i][$c]) > abs($m[$pivot][$c])) {
                    $pivot = $i;
                }
            }
            if (abs($m[$pivot][$c]) < 1e-9) {
                return null;
            }
            [$m[$c], $m[$pivot]] = [$m[$pivot], $m[$c]];
            for ($i = 0; $i < $k; ++$i) {
                if ($i !== $c) {
                    $f = $m[$i][$c] / $m[$c][$c];
                    for ($j = $c; $j <= $k; ++$j) {
                        $m[$i][$j] -= $f * $m[$c][$j];
                    }
                }
            }
        }

        $x = [];
        for ($i = 0; $i < $k; ++$i) {
            $x[] = $m[$i][$k] / $m[$i][$i];
        }

        return $x;
    }
}
