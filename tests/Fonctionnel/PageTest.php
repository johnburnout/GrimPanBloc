<?php

namespace App\Tests\Fonctionnel;

use App\MiseAJour\VersionInstallee;

/** Gabarit commun à toutes les pages. */
final class PageTest extends FonctionnelTestCase
{
    /** La feuille de style change d'adresse à chaque version : pas de vieille copie en cache. */
    public function testLaFeuilleDeStylePorteLaVersion(): void
    {
        $version = static::getContainer()->get(VersionInstallee::class)->get();

        $page = $this->client->request('GET', '/secteurs');

        self::assertResponseIsSuccessful();
        self::assertSame("/css/app.css?v=$version", $page->filter('link[rel="stylesheet"]')->attr('href'));
        self::assertStringEndsWith('.woff2', (string) $page->filter('link[rel="preload"]')->attr('href'));
    }
}
