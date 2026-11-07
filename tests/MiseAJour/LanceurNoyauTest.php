<?php

namespace App\Tests\MiseAJour;

use App\MiseAJour\LanceurCommandes;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LanceurNoyauTest extends KernelTestCase
{
    private string|false $verbositeInitiale;

    /** phpunit.dist.xml rend la console muette (SHELL_VERBOSITY=-1) ; en production web, elle ne l'est pas. */
    protected function setUp(): void
    {
        $this->verbositeInitiale = getenv('SHELL_VERBOSITY');
        putenv('SHELL_VERBOSITY=0');
        $_ENV['SHELL_VERBOSITY'] = $_SERVER['SHELL_VERBOSITY'] = 0;
    }

    protected function tearDown(): void
    {
        putenv('SHELL_VERBOSITY='.$this->verbositeInitiale);
        $_ENV['SHELL_VERBOSITY'] = $_SERVER['SHELL_VERBOSITY'] = $this->verbositeInitiale;
        parent::tearDown();
    }

    public function testLanceUneCommandeEtRenvoieSaSortie(): void
    {
        $lanceur = static::getContainer()->get(LanceurCommandes::class);

        $resultat = $lanceur->lancer(['command' => 'debug:router', 'name' => 'admin_mise_a_jour_finaliser']);

        self::assertSame(0, $resultat['code']);
        self::assertStringContainsString('/admin/mise-a-jour/finaliser', $resultat['sortie']);
    }

    public function testRenvoieLeCodeDErreur(): void
    {
        $resultat = static::getContainer()->get(LanceurCommandes::class)->lancer(['command' => 'commande:inexistante']);

        self::assertNotSame(0, $resultat['code']);
    }
}
