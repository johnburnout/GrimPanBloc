<?php

namespace App\Tests\Recalage;

use App\Recalage\Homographie;
use App\Recalage\ModeleRecalage;
use PHPUnit\Framework\TestCase;

final class HomographieTest extends TestCase
{
    public function testSansPaireLaPhotoNeBougePas(): void
    {
        self::assertSame(Homographie::identite(), Homographie::estimer([]));
    }

    public function testUnePaireDonneUneTranslation(): void
    {
        $h = Homographie::estimer([[10, 20, 15, 18]]);

        self::assertPoint([55, 52], Homographie::appliquer($h, 50, 54));
    }

    public function testDeuxPairesDonnentUneSimilitude(): void
    {
        // Rotation d'un quart de tour autour de (50, 50)
        $h = Homographie::estimer([[60, 50, 50, 60], [50, 30, 70, 50]]);

        self::assertPoint([60, 50], Homographie::appliquer($h, 50, 40));
    }

    public function testTroisPairesDonnentUneAffine(): void
    {
        // u = 2x + 5, v = y / 2 + 10 (le cadrage change d'échelle différemment en x et en y)
        $f = fn (float $x, float $y) => [2 * $x + 5, $y / 2 + 10];
        $paires = array_map(fn (array $p) => [...$p, ...$f(...$p)], [[0, 0], [20, 0], [0, 40]]);
        $h = Homographie::estimer($paires);

        self::assertPoint($f(30, 30), Homographie::appliquer($h, 30, 30));
    }

    public function testQuatrePairesOuPlusRetrouventLaPerspective(): void
    {
        // Photo prise d'un autre point de vue : homographie connue
        $vraie = [0.9, 0.1, 5, -0.05, 1.1, 2, 0.001, -0.0005, 1];
        $paires = [];
        foreach ([[10.0, 10.0], [90.0, 15.0], [85.0, 90.0], [15.0, 80.0], [50.0, 50.0], [30.0, 70.0]] as [$x, $y]) {
            $paires[] = [$x, $y, ...Homographie::appliquer($vraie, $x, $y)];
        }
        $h = Homographie::estimer($paires);

        foreach ([[40, 20], [70, 60], [5, 95]] as [$x, $y]) {
            self::assertPoint(Homographie::appliquer($vraie, $x, $y), Homographie::appliquer($h, $x, $y));
        }
    }

    public function testDesPointsAlignesNeFontPasEchouerLeCalcul(): void
    {
        // Prises toutes sur une ligne : ni homographie ni affine possibles, repli sur la similitude
        $h = Homographie::estimer([[10, 10, 12, 10], [20, 20, 22, 20], [30, 30, 32, 30], [40, 40, 42, 40]]);

        self::assertPoint([52, 50], Homographie::appliquer($h, 50, 50));
        self::assertCount(9, $h);
    }

    public function testLaCorrectionLocaleSuitLesPrisesReplaceesALaMain(): void
    {
        // Translation de +10 en x, sauf une prise que le grimpeur a placée 3 % plus bas
        $paires = [[10, 10, 20, 10], [90, 10, 100, 10], [10, 90, 20, 90], [90, 90, 100, 90], [50, 50, 60, 53]];
        $modele = new ModeleRecalage(Homographie::estimer($paires), $paires);

        self::assertPoint([60, 53], $modele->transformer(50, 50), 0.3, 'sur la prise replacée');
        $loin = $modele->transformer(50, 80);
        self::assertEqualsWithDelta(80.5, $loin[1], 0.6, 'loin de la prise replacée, la correction s\'efface');
    }

    /**
     * @param array{float|int, float|int} $attendu
     * @param array{float, float} $obtenu
     */
    private static function assertPoint(array $attendu, array $obtenu, float $delta = 0.01, string $message = ''): void
    {
        self::assertEqualsWithDelta($attendu[0], $obtenu[0], $delta, $message);
        self::assertEqualsWithDelta($attendu[1], $obtenu[1], $delta, $message);
    }
}
