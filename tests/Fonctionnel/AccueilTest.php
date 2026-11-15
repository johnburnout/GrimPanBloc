<?php

namespace App\Tests\Fonctionnel;

/** Accueil : partage de l'adresse du site par QR code. */
final class AccueilTest extends FonctionnelTestCase
{
    public function testLeBoutonPartagerMontreLeQrCodeDeLAdresseDuSite(): void
    {
        $page = $this->client->request('GET', '/?vue=liste&tri=nom', server: ['HTTP_HOST' => 'grimpe.exemple.test']);

        self::assertResponseIsSuccessful();
        $partage = $page->filter('details.partage');
        self::assertSame('Partager', $partage->filter('summary')->text());
        self::assertCount(1, $partage->filter('.qr svg'), 'QR code en SVG dans la page, sans service extérieur');
        // L'adresse partagée est celle de l'accueil, sans les filtres de la page affichée
        self::assertSame('http://grimpe.exemple.test/', $partage->filter('.partage-url')->text());
    }

    public function testLeQrCodeVisibleAussiPourUnVisiteurConnecte(): void
    {
        $this->client->loginUser($this->creerUtilisateur('grimpeur'));
        $page = $this->client->request('GET', '/');

        self::assertCount(1, $page->filter('details.partage .qr svg'));
    }
}
