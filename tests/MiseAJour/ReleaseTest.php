<?php

namespace App\Tests\MiseAJour;

use App\MiseAJour\Release;
use PHPUnit\Framework\TestCase;

final class ReleaseTest extends TestCase
{
    private static function api(string $tag, array $noms): array
    {
        return [
            'tag_name' => $tag,
            'assets' => array_map(fn (string $n) => ['name' => $n, 'browser_download_url' => "https://exemple.org/$n", 'size' => 42], $noms),
        ];
    }

    public function testNouveauNomPrefere(): void
    {
        $r = Release::depuisApi(self::api('v1.3.0', [
            'grimpepan-v1.3.0.zip', 'grimpepan-v1.3.0.zip.sha256', 'grimpanbloc-v1.3.0.zip', 'grimpanbloc-v1.3.0.zip.sha256',
        ]));
        self::assertTrue($r->estInstallable());
        self::assertSame('https://exemple.org/grimpanbloc-v1.3.0.zip', $r->urlArchive);
        self::assertSame('https://exemple.org/grimpanbloc-v1.3.0.zip.sha256', $r->urlEmpreinte);
        self::assertSame('1.3.0', $r->version);
    }

    public function testAncienNomAccepte(): void
    {
        $r = Release::depuisApi(self::api('v1.2.0', ['grimpepan-v1.2.0.zip', 'grimpepan-v1.2.0.zip.sha256']));
        self::assertTrue($r->estInstallable());
        self::assertSame('https://exemple.org/grimpepan-v1.2.0.zip', $r->urlArchive);
    }

    public function testArchiveEtEmpreinteDuMemeNom(): void
    {
        // Une archive sans son empreinte ne doit pas être associée à l'empreinte de l'autre nom.
        $r = Release::depuisApi(self::api('v1.3.0', ['grimpanbloc-v1.3.0.zip', 'grimpepan-v1.3.0.zip.sha256']));
        self::assertFalse($r->estInstallable());
    }
}
