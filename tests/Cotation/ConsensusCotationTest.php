<?php

namespace App\Tests\Cotation;

use App\Cotation\ConsensusCotation;
use App\Entity\Croix;
use App\Entity\User;
use App\Entity\Voie;
use App\Enum\CotationFr;
use App\Enum\SystemeCotation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConsensusCotationTest extends TestCase
{
    /** @return iterable<string, array{list<string>, string, string}> */
    public static function cas(): iterable
    {
        yield 'aucun validateur : proposition du créateur' => [[], '6a', '6a'];
        yield 'un seul validateur' => [['6b'], '6a', '6b'];
        yield 'majorité nette' => [['6b', '6b', '6c'], '6a', '6b'];
        yield 'la majorité l\'emporte sur le créateur' => [['6c', '6c', '6a'], '6a', '6c'];
        yield 'égalité : le créateur départage' => [['6a', '6b'], '6b', '6b'];
        yield 'égalité sans le créateur : médiane (3)' => [['5c', '7a', '6b'], '6a', '6b'];
        yield 'égalité sans le créateur : médiane côté facile (2)' => [['7a', '6b'], '5a', '6b'];
        yield 'égalité sans le créateur : médiane côté facile (4)' => [['5a', '8a', '6a', '7a'], '4a', '6a'];
        yield 'égalité à deux voix chacune' => [['6a', '6c', '6a', '6c', '7a'], '6c', '6c'];
    }

    /** @param list<string> $votes */
    #[DataProvider('cas')]
    public function testCalculer(array $votes, string $auteur, string $attendu): void
    {
        self::assertSame($attendu, ConsensusCotation::calculer($votes, $auteur, SystemeCotation::Francaise->rang(...)));
    }

    public function testRepartitionClasseeDuPlusFacileAuPlusDur(): void
    {
        self::assertSame(['5c' => 1, '6b' => 2, '7a' => 1],
            ConsensusCotation::repartition(['7a', '6b', '5c', '6b'], SystemeCotation::Francaise->rang(...)));
    }

    public function testCouleurs(): void
    {
        self::assertSame('bleu', ConsensusCotation::calculer(['vert', 'noir', 'bleu'], 'jaune', SystemeCotation::Couleur->rang(...)));
    }

    /** Intégration avec l'entité : seules les croix des autres grimpeurs votent. */
    public function testVoieRecalculeLaCotationAffichee(): void
    {
        $auteur = (new User())->setEmail('a@example.org')->setPseudo('auteur');
        $voie = (new Voie())->setAuteur($auteur)->setSysteme(SystemeCotation::Francaise)->setCotationFr(CotationFr::C6a);
        self::assertSame('6a', $voie->getCotation());
        self::assertSame(CotationFr::C6a->rang(), $voie->getRang());

        // La croix de l'auteur ne vote pas
        $voie->ajouterCroix(new Croix($voie, $auteur));
        self::assertSame('6a', $voie->getCotation());

        $b = (new User())->setEmail('b@example.org')->setPseudo('b');
        $c = (new User())->setEmail('c@example.org')->setPseudo('c');
        $croixB = (new Croix($voie, $b))->setCotation('6c');
        $voie->ajouterCroix($croixB);
        self::assertSame('6c', $voie->getCotation(), 'un seul validateur : sa proposition');
        self::assertSame(CotationFr::C6c->rang(), $voie->getRang(), 'le rang suit (tri et filtres)');

        $voie->ajouterCroix((new Croix($voie, $c))->setCotation('6a'));
        self::assertSame('6a', $voie->getCotation(), 'égalité 6a / 6c : le créateur (6a) départage');

        // Un validateur change d'avis
        $croixB->setCotation('6a');
        $voie->ajouterCroix($croixB);
        self::assertSame('6a', $voie->getCotation());
        self::assertSame(['6a' => 2], array_column(array_map(fn ($r) => [$r['cotation']->value, $r['nombre']], $voie->getRepartitionCotations()), 1, 0));

        // Toutes les croix des validateurs retirées : retour à la proposition du créateur
        foreach ($voie->getCroixValidantes() as $croix) {
            $voie->retirerCroix($croix);
        }
        $voie->setCotationFr(CotationFr::C5c);
        self::assertSame('5c', $voie->getCotation());
    }
}
