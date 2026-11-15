<?php

namespace App\Tests\MiseAJour;

use App\MiseAJour\VerificateurMiseAJour;
use App\MiseAJour\VersionInstallee;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Lecture de la dernière release : par l'API GitHub, ou par le site github.com
 * quand l'API refuse (quota de 60 requêtes par heure et par IP épuisé).
 */
final class VerificateurMiseAJourTest extends TestCase
{
    private const API = 'https://api.github.com/repos/club/grimpanbloc';
    private const JOURNAL = <<<'MD'
        # Journal des versions

        ## [1.11.0] - 2026-11-15

        ### Ajouté
        - Page « Informations ».

        ## [1.10.0] - 2026-11-15

        ### Ajouté
        - Pieds libres.
        MD;

    /** @var list<string> */
    private array $requetes = [];
    private ?string $racine = null;

    protected function tearDown(): void
    {
        if ($this->racine) {
            (new Filesystem())->remove($this->racine);
        }
    }

    public function testLaReleaseVientDeLApiQuandElleRepond(): void
    {
        $release = $this->verificateur([
            self::API.'/releases/latest' => new MockResponse((string) json_encode(['tag_name' => 'v1.11.0', 'body' => 'Notes API', 'assets' => []])),
        ])->derniereRelease();

        self::assertSame('1.11.0', $release?->version);
        self::assertSame('Notes API', $release->notes);
        self::assertSame([self::API.'/releases/latest'], $this->requetes);
    }

    public function testQuotaDeLApiEpuiseLaReleaseEstLueSurGithub(): void
    {
        $verificateur = $this->verificateur([
            self::API.'/releases/latest' => $this->quotaEpuise(),
            'https://github.com/club/grimpanbloc/releases/latest' => $this->redirection('https://github.com/club/grimpanbloc/releases/tag/v1.11.0'),
            'https://raw.githubusercontent.com/club/grimpanbloc/v1.11.0/CHANGELOG.md' => new MockResponse(self::JOURNAL),
        ]);

        $release = $verificateur->miseAJourDisponible();

        self::assertNotNull($release, 'La 1.11.0 est plus récente que la 1.10.0 installée.');
        self::assertSame('v1.11.0', $release->tag);
        self::assertSame('GrimPanBloc 1.11.0', $release->nom);
        self::assertSame("### Ajouté\n- Page « Informations ».", $release->notes);
        self::assertSame('https://github.com/club/grimpanbloc/releases/tag/v1.11.0', $release->url);
        self::assertTrue($release->estInstallable());
        self::assertSame('https://github.com/club/grimpanbloc/releases/download/v1.11.0/grimpanbloc-v1.11.0.zip', $release->urlArchive);
        self::assertSame('https://github.com/club/grimpanbloc/releases/download/v1.11.0/grimpanbloc-v1.11.0.zip.sha256', $release->urlEmpreinte);

        // Résultat gardé en cache : pas de nouvelle requête
        $nombre = \count($this->requetes);
        $verificateur->derniereRelease();
        self::assertCount($nombre, $this->requetes);
    }

    public function testSansNotesLaReleaseResteInstallable(): void
    {
        $release = $this->verificateur([
            self::API.'/releases/latest' => $this->quotaEpuise(),
            'https://github.com/club/grimpanbloc/releases/latest' => $this->redirection('https://github.com/club/grimpanbloc/releases/tag/v1.11.0'),
            'https://raw.githubusercontent.com/club/grimpanbloc/v1.11.0/CHANGELOG.md' => new MockResponse('', ['http_code' => 404]),
        ])->derniereRelease();

        self::assertSame('1.11.0', $release?->version);
        self::assertSame('', $release->notes);
        self::assertTrue($release->estInstallable());
    }

    public function testAucuneReleasePublieeSurGithub(): void
    {
        $release = $this->verificateur([
            self::API.'/releases/latest' => $this->quotaEpuise(),
            'https://github.com/club/grimpanbloc/releases/latest' => $this->redirection('https://github.com/club/grimpanbloc/releases'),
        ])->derniereRelease();

        self::assertNull($release);
    }

    public function testDepotHorsGithubPasDeSecours(): void
    {
        $release = $this->verificateur([
            'https://git.exemple.test/api/releases/latest' => $this->quotaEpuise(),
        ], 'https://git.exemple.test/api')->derniereRelease();

        self::assertNull($release);
        self::assertSame(['https://git.exemple.test/api/releases/latest'], $this->requetes);
    }

    // ------------------------------------------------------------------------

    /** @param array<string, MockResponse> $reponses */
    private function verificateur(array $reponses, string $api = self::API): VerificateurMiseAJour
    {
        $client = new MockHttpClient(function (string $methode, string $url) use ($reponses): MockResponse {
            $this->requetes[] = $url;

            return $reponses[$url] ?? throw new \LogicException("Requête inattendue vers $url");
        });
        $this->racine = sys_get_temp_dir().'/grimpanbloc-version-'.bin2hex(random_bytes(4));
        (new Filesystem())->dumpFile($this->racine.'/VERSION', "1.10.0\n");

        return new VerificateurMiseAJour($client, new ArrayAdapter(), new VersionInstallee($this->racine), new NullLogger(), $api);
    }

    private function quotaEpuise(): MockResponse
    {
        return new MockResponse('{"message":"API rate limit exceeded for 203.0.113.7."}', ['http_code' => 403]);
    }

    private function redirection(string $destination): MockResponse
    {
        return new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => $destination]]);
    }
}
