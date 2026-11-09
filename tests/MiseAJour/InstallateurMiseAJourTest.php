<?php

namespace App\Tests\MiseAJour;

use App\MiseAJour\ArchiveRelease;
use App\MiseAJour\InstallateurMiseAJour;
use App\MiseAJour\Maintenance;
use App\MiseAJour\Release;
use App\MiseAJour\VersionInstallee;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Installation d'une release sur une fausse installation (dossier temporaire),
 * avec une archive servie par un faux serveur HTTP.
 */
final class InstallateurMiseAJourTest extends TestCase
{
    private const URL_ZIP = 'https://exemple.test/grimpanbloc-v1.1.0.zip';
    private const URL_SHA = 'https://exemple.test/grimpanbloc-v1.1.0.zip.sha256';

    private string $racine;
    private Filesystem $fs;
    /** @var array<string, string> fichiers de l'archive (chemin => contenu) */
    private array $archive;
    private ?string $empreinte = null;
    /** @var list<string> */
    private array $journal = [];

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->racine = sys_get_temp_dir().'/grimpanbloc-installation-'.bin2hex(random_bytes(4));
        $this->ecrire([
            'VERSION' => "1.0.0\n",
            '.env' => 'ancien',
            '.env.local' => 'secrets',
            'bin/console' => 'ancien',
            'src/Kernel.php' => 'ancien',
            'vendor/autoload_runtime.php' => 'ancien',
            'public/index.php' => 'ancien',
            'public/uploads/.htaccess' => 'ancien',
            'public/uploads/photos/mur.jpg' => 'photo',
            'public/setup/index.php' => 'assistant',
            'public/assets/app.css' => 'compilé',
            'var/donnees.txt' => 'données',
            'var/cache/prod/conteneur.php' => 'cache',
        ], $this->racine);

        $this->archive = [
            'grimpanbloc/VERSION' => "1.1.0\n",
            'grimpanbloc/.env' => 'nouveau',
            'grimpanbloc/.env.local' => 'ne doit pas écraser',
            'grimpanbloc/bin/console' => 'nouveau',
            'grimpanbloc/src/Kernel.php' => 'nouveau',
            'grimpanbloc/src/Nouveau.php' => 'nouveau',
            'grimpanbloc/vendor/autoload_runtime.php' => 'nouveau',
            'grimpanbloc/public/index.php' => 'nouveau',
            'grimpanbloc/public/uploads/.htaccess' => 'nouveau',
            'grimpanbloc/public/setup/index.php' => 'nouvel assistant',
            'grimpanbloc/var/donnees.txt' => 'ne doit pas écraser',
        ];
    }

    protected function tearDown(): void
    {
        if (is_dir($this->racine.'/vendor')) {
            @chmod($this->racine.'/vendor', 0755);
        }
        $this->fs->remove($this->racine);
    }

    public function testInstallationComplete(): void
    {
        $jeton = $this->installer();

        // Nouveau code en place
        self::assertSame("1.1.0\n", $this->lire('VERSION'));
        foreach (['.env', 'bin/console', 'src/Kernel.php', 'src/Nouveau.php', 'vendor/autoload_runtime.php', 'public/index.php', 'public/uploads/.htaccess'] as $f) {
            self::assertSame('nouveau', $this->lire($f), $f);
        }
        // Jamais touchés
        self::assertSame('secrets', $this->lire('.env.local'));
        self::assertSame('données', $this->lire('var/donnees.txt'));
        self::assertSame('photo', $this->lire('public/uploads/photos/mur.jpg'));
        self::assertSame('assistant', $this->lire('public/setup/index.php'));

        // Ancienne version sauvegardée, logos compilés et cache écartés
        $sauvegardes = glob($this->racine.'/var/mise-a-jour/precedente-1.0.0-*', GLOB_ONLYDIR);
        self::assertCount(1, $sauvegardes);
        self::assertSame('ancien', file_get_contents($sauvegardes[0].'/src/Kernel.php'));
        self::assertSame('compilé', file_get_contents($sauvegardes[0].'/public/assets/app.css'));
        self::assertDirectoryDoesNotExist($this->racine.'/public/assets');
        self::assertDirectoryDoesNotExist($this->racine.'/var/cache/prod');

        // Fichiers de travail supprimés, maintenance active jusqu'à la finalisation
        self::assertFileDoesNotExist($this->racine.'/var/mise-a-jour/telechargement.zip');
        self::assertDirectoryDoesNotExist($this->racine.'/var/mise-a-jour/nouvelle');
        self::assertTrue((new Maintenance($this->racine))->jetonValide($jeton));
        self::assertCount(5, $this->journal);
    }

    public function testArchiveAvecLAncienNomDeDossier(): void
    {
        $archive = [];
        foreach ($this->archive as $chemin => $contenu) {
            $archive['grimpepan/'.substr($chemin, \strlen('grimpanbloc/'))] = $contenu;
        }
        $this->archive = $archive;

        $this->installer();

        self::assertSame('nouveau', $this->lire('src/Kernel.php'));
    }

    public function testUneSeuleSauvegardeEstConservee(): void
    {
        $this->fs->mkdir($this->racine.'/var/mise-a-jour/precedente-0.9.0-20260101-000000');

        $this->installer();

        self::assertCount(1, glob($this->racine.'/var/mise-a-jour/precedente-*', GLOB_ONLYDIR));
    }

    public function testEmpreinteIncorrecte(): void
    {
        $this->empreinte = str_repeat('0', 64);

        $this->assertEchec('L\'empreinte SHA-256 de l\'archive ne correspond pas');
        self::assertFileDoesNotExist($this->racine.'/var/mise-a-jour/telechargement.zip');
    }

    public function testCheminInterditDansLArchive(): void
    {
        $this->archive['grimpanbloc/../../evade.php'] = 'pirate';

        $this->assertEchec('Chemin interdit dans l\'archive');
        self::assertFileDoesNotExist(\dirname($this->racine).'/evade.php');
    }

    public function testVersionDeLArchiveDifferente(): void
    {
        $this->archive['grimpanbloc/VERSION'] = "1.2.0\n";

        $this->assertEchec('L\'archive annonce la version « 1.2.0 » au lieu de 1.1.0');
    }

    public function testArchiveIncomplete(): void
    {
        unset($this->archive['grimpanbloc/vendor/autoload_runtime.php']);

        $this->assertEchec('Archive incomplète : vendor/autoload_runtime.php est absent.');
    }

    public function testReleaseQuiNEstPasPlusRecente(): void
    {
        file_put_contents($this->racine.'/VERSION', '1.1.0');

        $this->assertEchec('n\'est pas plus récente que la version installée', inchange: false);
    }

    public function testRemplacementInterrompuRemetLAncienCodeEnPlace(): void
    {
        // Un dossier qu'on ne peut pas déplacer : le remplacement échoue sur « vendor »,
        // après « .env », « bin » et « src »
        chmod($this->racine.'/vendor', 0555);

        $this->assertEchec('Mise à jour annulée, l\'ancienne version est restée en place');
        self::assertSame('ancien', $this->lire('.env'));
        self::assertSame('ancien', $this->lire('bin/console'));
        self::assertFileDoesNotExist($this->racine.'/src/Nouveau.php');
        self::assertFileDoesNotExist((new Maintenance($this->racine))->fichier(), 'La maintenance est levée.');
    }

    // ------------------------------------------------------------------------

    private function installer(): string
    {
        return $this->installateur()->installer($this->release(), function (string $ligne): void { $this->journal[] = $ligne; });
    }

    /** L'installation échoue et laisse le code en place. */
    private function assertEchec(string $message, bool $inchange = true): void
    {
        try {
            $this->installer();
            self::fail('L\'installation aurait dû échouer.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString($message, $e->getMessage());
        }
        if ($inchange) {
            self::assertSame("1.0.0\n", $this->lire('VERSION'));
            self::assertSame('ancien', $this->lire('src/Kernel.php'));
        }
        self::assertFileDoesNotExist((new Maintenance($this->racine))->fichier());
    }

    private function installateur(): InstallateurMiseAJour
    {
        $zip = $this->construireZip();
        $empreinte = $this->empreinte ?? hash('sha256', $zip);
        $client = new MockHttpClient(fn (string $methode, string $url) => match ($url) {
            self::URL_ZIP => new MockResponse($zip),
            self::URL_SHA => new MockResponse("$empreinte  grimpanbloc-v1.1.0.zip\n"),
            default => throw new \LogicException("Requête inattendue vers $url"),
        });

        $version = new VersionInstallee($this->racine);

        return new InstallateurMiseAJour(new ArchiveRelease($client, $version), $version, new Maintenance($this->racine), $this->racine);
    }

    private function release(): Release
    {
        return new Release('1.1.0', 'v1.1.0', 'v1.1.0', '', '', null, self::URL_ZIP, self::URL_SHA, 0);
    }

    private function construireZip(): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'release');
        $zip = new \ZipArchive();
        $zip->open($chemin, \ZipArchive::OVERWRITE);
        foreach ($this->archive as $nom => $contenu) {
            $zip->addFromString($nom, $contenu);
        }
        $zip->close();
        $octets = file_get_contents($chemin);
        unlink($chemin);

        return $octets;
    }

    /** @param array<string, string> $fichiers */
    private function ecrire(array $fichiers, string $dossier): void
    {
        foreach ($fichiers as $chemin => $contenu) {
            $this->fs->dumpFile($dossier.'/'.$chemin, $contenu);
        }
    }

    private function lire(string $chemin): string
    {
        return (string) file_get_contents($this->racine.'/'.$chemin);
    }
}
