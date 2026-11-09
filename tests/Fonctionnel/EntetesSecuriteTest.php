<?php

namespace App\Tests\Fonctionnel;

use App\EventSubscriber\EntetesSecuriteListener;

/**
 * En-têtes de sécurité HTTP sur les réponses de l'application.
 */
final class EntetesSecuriteTest extends FonctionnelTestCase
{
    public function testPageNormale(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $this->assertEntetesDeSecurite();
    }

    public function testRedirection(): void
    {
        $this->client->request('GET', '/profil');

        self::assertResponseRedirects('/connexion');
        $this->assertEntetesDeSecurite();
    }

    public function testPageIntrouvable(): void
    {
        $this->client->request('GET', '/voies/999999999');

        self::assertResponseStatusCodeSame(404);
        // En mode debug (tests), l'ErrorListener de Symfony retire exprès le CSP des
        // pages d'erreur pour que sa page d'exception fonctionne ; il reste en production.
        $this->assertEntetesDeSecurite(sauf: 'Content-Security-Policy');
    }

    private function assertEntetesDeSecurite(?string $sauf = null): void
    {
        foreach (EntetesSecuriteListener::ENTETES as $nom => $valeur) {
            if ($nom === $sauf) {
                continue;
            }
            self::assertResponseHeaderSame($nom, $valeur);
        }
    }
}
