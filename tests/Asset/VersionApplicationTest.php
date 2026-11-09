<?php

namespace App\Tests\Asset;

use App\Asset\VersionApplication;
use App\MiseAJour\VersionInstallee;
use PHPUnit\Framework\TestCase;

final class VersionApplicationTest extends TestCase
{
    private string $racine;

    protected function setUp(): void
    {
        $this->racine = sys_get_temp_dir().'/grimpanbloc-version-'.bin2hex(random_bytes(4));
        mkdir($this->racine);
        file_put_contents($this->racine.'/VERSION', "1.3.9\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->racine.'/VERSION');
        @rmdir($this->racine);
    }

    public function testFeuillesDeStyleEtScriptsVersionnes(): void
    {
        $strategie = new VersionApplication(new VersionInstallee($this->racine));

        self::assertSame('css/app.css?v=1.3.9', $strategie->applyVersion('css/app.css'));
        self::assertSame('js/editeur.js?v=1.3.9', $strategie->applyVersion('js/editeur.js'));
        self::assertSame('css/app.css?a=1&v=1.3.9', $strategie->applyVersion('css/app.css?a=1'));
    }

    public function testAutresFichiersInchanges(): void
    {
        $strategie = new VersionApplication(new VersionInstallee($this->racine));

        foreach (['uploads/photos/mur.jpg', 'uploads/logo/club.png', 'fonts/police.woff2'] as $chemin) {
            self::assertSame($chemin, $strategie->applyVersion($chemin));
        }
    }
}
