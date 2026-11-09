<?php

namespace App\Tests\Fonctionnel;

use App\MiseAJour\ArchiveRelease;
use App\MiseAJour\InstallateurMiseAJour;
use App\MiseAJour\LanceurCommandes;
use App\MiseAJour\Maintenance;
use App\MiseAJour\VerificateurMiseAJour;
use App\MiseAJour\VersionInstallee;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

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

    /**
     * Parcours complet depuis l'administration : « Mettre à jour » installe la release
     * (fausse installation dans le dossier temporaire), puis la page renvoyée poste le
     * jeton à la finalisation, sans qu'il n'apparaisse jamais dans une URL.
     */
    public function testInstallerPuisFinaliserEnPost(): void
    {
        $this->simulerReleaseDisponible();
        $this->client->loginUser($this->creerUtilisateur('admin', admin: true));
        $page = $this->client->request('GET', '/admin/mise-a-jour');

        $this->client->submit($page->selectButton('Mettre à jour vers la version 9.9.9')->form());

        self::assertResponseIsSuccessful();
        self::assertSame('9.9.9', trim((string) file_get_contents($this->dossier.'/VERSION')), 'Code remplacé.');
        // La page porte le jeton : jamais mise en cache (la session ajoute ses propres directives)
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $form = $this->client->getCrawler()->filter('form#finaliser');
        self::assertSame('post', strtolower((string) $form->attr('method')));
        self::assertSame('/admin/mise-a-jour/finaliser', $form->attr('action'));
        $jeton = (string) $form->filter('input[name="jeton"]')->attr('value');
        self::assertTrue($this->maintenance->jetonValide($jeton));

        $this->client->submit($form->form());

        self::assertResponseRedirects('/admin/mise-a-jour');
        self::assertSame(['doctrine:migrations:migrate', 'asset-map:compile'], array_column($this->lancees, 'command'));
        self::assertFileDoesNotExist($this->maintenance->fichier(), 'La maintenance est terminée.');
    }

    public function testFinalisationReussie(): void
    {
        $jeton = $this->maintenance->activer();

        $this->client->request('POST', '/admin/mise-a-jour/finaliser', ['jeton' => $jeton]);

        self::assertResponseRedirects('/admin/mise-a-jour');
        self::assertSame(['doctrine:migrations:migrate', 'asset-map:compile'], array_column($this->lancees, 'command'));
        self::assertTrue($this->lancees[0]['--allow-no-migration']);
        $version = static::getContainer()->get(VersionInstallee::class)->get();
        self::assertSame(["GrimPanBloc est passé de la version ? à la version $version."], $this->flashs('success'));
        self::assertFileDoesNotExist($this->maintenance->fichier(), 'La maintenance est terminée.');
    }

    /** Contrat entre versions : jusqu'à la 1.3.2, l'ancienne version envoie le jeton dans l'URL. */
    public function testFinalisationEnGetDepuisUneVersionAnterieure(): void
    {
        $jeton = $this->maintenance->activer();

        $this->client->request('GET', '/admin/mise-a-jour/finaliser?jeton='.$jeton);

        self::assertResponseRedirects('/admin/mise-a-jour');
        self::assertCount(2, $this->lancees);
        self::assertFileDoesNotExist($this->maintenance->fichier());
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

        $essais = [
            ['GET', '/admin/mise-a-jour/finaliser?jeton=faux', []],
            ['GET', '/admin/mise-a-jour/finaliser', []],
            ['POST', '/admin/mise-a-jour/finaliser', ['jeton' => 'faux']],
            ['POST', '/admin/mise-a-jour/finaliser', []],
        ];
        foreach ($essais as [$methode, $url, $donnees]) {
            $this->client->request($methode, $url, $donnees);
            self::assertResponseRedirects('/admin/mise-a-jour');
            self::assertContains('Lien de finalisation invalide ou expiré.', $this->flashs('error'));
        }
        self::assertSame([], $this->lancees);
        self::assertFileExists($this->maintenance->fichier(), 'Un mauvais jeton ne lève pas la maintenance.');
    }

    /**
     * Fausse installation 1.0.0 dans le dossier temporaire, et une release 9.9.9
     * servie par un faux GitHub, installables par les vrais services.
     */
    private function simulerReleaseDisponible(): void
    {
        $fs = new Filesystem();
        foreach (['VERSION' => "1.0.0\n", 'bin/console' => 'ancien', 'src/Kernel.php' => 'ancien',
            'vendor/autoload_runtime.php' => 'ancien', 'public/index.php' => 'ancien'] as $chemin => $contenu) {
            $fs->dumpFile($this->dossier.'/'.$chemin, $contenu);
        }

        $chemin = tempnam(sys_get_temp_dir(), 'release');
        $zip = new \ZipArchive();
        $zip->open($chemin, \ZipArchive::OVERWRITE);
        foreach (['VERSION' => "9.9.9\n", 'bin/console' => 'nouveau', 'src/Kernel.php' => 'nouveau',
            'vendor/autoload_runtime.php' => 'nouveau', 'public/index.php' => 'nouveau'] as $nom => $contenu) {
            $zip->addFromString('grimpanbloc/'.$nom, $contenu);
        }
        $zip->close();
        $octets = (string) file_get_contents($chemin);
        unlink($chemin);

        $base = 'https://exemple.test/grimpanbloc-v9.9.9.zip';
        $api = [
            'tag_name' => 'v9.9.9',
            'assets' => [
                ['name' => 'grimpanbloc-v9.9.9.zip', 'browser_download_url' => $base, 'size' => \strlen($octets)],
                ['name' => 'grimpanbloc-v9.9.9.zip.sha256', 'browser_download_url' => $base.'.sha256'],
            ],
        ];
        $client = new MockHttpClient(fn (string $methode, string $url) => match (true) {
            str_ends_with($url, '/releases/latest') => new MockResponse((string) json_encode($api)),
            $url === $base => new MockResponse($octets),
            $url === $base.'.sha256' => new MockResponse(hash('sha256', $octets).'  grimpanbloc-v9.9.9.zip'),
            default => throw new \LogicException("Requête inattendue vers $url"),
        });

        $version = new VersionInstallee($this->dossier);
        $conteneur = static::getContainer();
        $conteneur->set(VerificateurMiseAJour::class, new VerificateurMiseAJour($client, new ArrayAdapter(), $version, new NullLogger(), 'https://api.exemple.test/repos/club/grimpanbloc'));
        $conteneur->set(InstallateurMiseAJour::class, new InstallateurMiseAJour(new ArchiveRelease($client, $version), $version, $this->maintenance, $this->dossier));
        $conteneur->set(VersionInstallee::class, $version);
    }

    /** @return list<string> messages flash (lus et retirés de la session) */
    private function flashs(string $type): array
    {
        return $this->client->getRequest()->getSession()->getFlashBag()->get($type);
    }
}
