<?php

namespace App\Tests\Fonctionnel;

use App\MiseAJour\LanceurCommandes;
use App\MiseAJour\Maintenance;
use App\MiseAJour\VersionInstallee;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Finalisation d'une mise à jour (route exécutée par la NOUVELLE version).
 * Le verrou de maintenance est écrit dans un dossier temporaire, jamais dans
 * var/ du projet (le site de dev passerait en maintenance), et les commandes
 * sont simulées.
 */
final class AdminMiseAJourTest extends FonctionnelTestCase
{
    private string $dossier;
    private Maintenance $maintenance;
    /** @var list<array<string, mixed>> */
    private array $lancees = [];
    /** @var array<string, int> code de sortie par commande (0 par défaut) */
    private array $codes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dossier = sys_get_temp_dir().'/grimpanbloc-test-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->dossier.'/var');
        $this->maintenance = new Maintenance($this->dossier);
        static::getContainer()->set(Maintenance::class, $this->maintenance);

        $test = $this;
        static::getContainer()->set(LanceurCommandes::class, new class($test) implements LanceurCommandes {
            public function __construct(private readonly AdminMiseAJourTest $test)
            {
            }

            public function lancer(array $arguments): array
            {
                return $this->test->lancer($arguments);
            }
        });
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dossier);
        parent::tearDown();
    }

    /** @internal appelé par le faux lanceur */
    public function lancer(array $arguments): array
    {
        $this->lancees[] = $arguments;
        $code = $this->codes[$arguments['command']] ?? 0;

        return ['code' => $code, 'sortie' => 0 === $code ? 'ok' : 'erreur SQL'];
    }

    public function testFinalisationReussie(): void
    {
        $jeton = $this->maintenance->activer();

        $this->client->request('GET', '/admin/mise-a-jour/finaliser?jeton='.$jeton);

        self::assertResponseRedirects('/admin/mise-a-jour');
        self::assertSame(['doctrine:migrations:migrate', 'asset-map:compile'], array_column($this->lancees, 'command'));
        self::assertTrue($this->lancees[0]['--allow-no-migration']);
        $version = static::getContainer()->get(VersionInstallee::class)->get();
        self::assertSame(["GrimPanBloc est passé de la version ? à la version $version."], $this->flashs('success'));
        self::assertFileDoesNotExist($this->maintenance->fichier(), 'La maintenance est terminée.');
    }

    public function testEchecDesMigrations(): void
    {
        $this->codes['doctrine:migrations:migrate'] = 1;
        $jeton = $this->maintenance->activer();

        $this->client->request('GET', '/admin/mise-a-jour/finaliser?jeton='.$jeton);

        self::assertResponseRedirects('/admin/mise-a-jour');
        self::assertSame(["Les migrations de la base ont échoué :\nerreur SQL"], $this->flashs('error'));
        self::assertSame([], $this->flashs('success'));
        self::assertCount(1, $this->lancees, 'Pas de compilation des logos après un échec des migrations.');
        self::assertFileDoesNotExist($this->maintenance->fichier(), 'La maintenance est levée même en cas d\'échec.');
    }

    public function testEchecDeLaCompilationDesLogosNEstQuUnAvertissement(): void
    {
        $this->codes['asset-map:compile'] = 1;
        $jeton = $this->maintenance->activer();

        $this->client->request('GET', '/admin/mise-a-jour/finaliser?jeton='.$jeton);

        self::assertStringContainsString('asset-map:compile', $this->flashs('error')[0] ?? '');
        self::assertCount(1, $this->flashs('success'));
    }

    public function testJetonInvalideOuAbsent(): void
    {
        $this->maintenance->activer();

        foreach (['/admin/mise-a-jour/finaliser?jeton=faux', '/admin/mise-a-jour/finaliser'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/admin/mise-a-jour');
            self::assertContains('Lien de finalisation invalide ou expiré.', $this->flashs('error'));
        }
        self::assertSame([], $this->lancees);
        self::assertFileExists($this->maintenance->fichier(), 'Un mauvais jeton ne lève pas la maintenance.');
    }

    /** @return list<string> messages flash (lus et retirés de la session) */
    private function flashs(string $type): array
    {
        return $this->client->getRequest()->getSession()->getFlashBag()->get($type);
    }
}
