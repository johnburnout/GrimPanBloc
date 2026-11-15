<?php

namespace App\Tests\Fonctionnel;

use App\Site\InformationsSalle;

/** Page publique « Informations » : ce que l'administrateur a renseigné sur la salle. */
final class InformationsTest extends FonctionnelTestCase
{
    public function testPageAccessibleDepuisLeMenuSansEtreConnecte(): void
    {
        $page = $this->client->request('GET', '/secteurs');
        $this->client->click($page->filter('.entete nav a')->reduce(fn ($a) => 'Infos' === $a->text())->link());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Informations');
        self::assertSelectorTextContains('main', 'pas encore été renseignées');
        self::assertSelectorNotExists('main a[href="/admin/reglages"]', 'Pas de lien vers les réglages pour un visiteur.');
    }

    public function testUnAdministrateurEstInviteARenseignerLesInformations(): void
    {
        $this->client->loginUser($this->creerUtilisateur('admin', admin: true));
        $this->client->request('GET', '/informations');

        self::assertSelectorExists('main a[href="/admin/reglages"]');
    }

    public function testAfficheLesInformationsRenseignees(): void
    {
        $this->enregistrer([
            'adresse' => "Gymnase\n12 route du Causse",
            'coordonnees' => '44.8312, 1.3254',
            'site_web' => 'https://grimpeurs-du-causse.fr/',
            'horaires_affiches' => true,
            'horaires' => ['mardi' => '18 h – 22 h'],
            'horaires_remarque' => 'Fermé en août',
            'hauteur' => 4.5,
            'surface' => 1250,
        ]);

        $page = $this->client->request('GET', '/informations');

        self::assertResponseIsSuccessful();
        self::assertSame('Gymnase 12 route du Causse', $page->filter('address')->text());
        self::assertCount(1, $page->filter('address br'));
        $carte = (string) $page->filter('iframe.carte-salle')->attr('src');
        self::assertStringStartsWith('https://www.openstreetmap.org/export/embed.html?', $carte);
        self::assertStringContainsString('marker=44.8312,1.3254', $carte);
        self::assertSelectorExists('a[href*="google.com/maps/dir/"][href*="destination=44.8312%2C1.3254"]');
        self::assertSelectorTextSame('a.site-salle', 'grimpeurs-du-causse.fr');
        self::assertSame('https://grimpeurs-du-causse.fr/', $page->filter('a.site-salle')->attr('href'));
        self::assertSame(['Hauteur', '4,5 m', 'Surface grimpable', '1 250 m²'], $page->filter('.caracteristiques dt, .caracteristiques dd')->each(fn ($n) => $n->text()));
        self::assertSame('18 h – 22 h', $page->filter('.horaires tr')->eq(1)->filter('td')->text());
        self::assertSame('Fermé', $page->filter('.horaires tr')->eq(0)->filter('td')->text());
        self::assertSelectorTextContains('main', 'Fermé en août');
    }

    /** La case décochée masque les horaires sans les effacer. */
    public function testLesHorairesNeSAffichentQueSiLaCaseEstCochee(): void
    {
        $this->enregistrer(['adresse' => '1 rue du Rocher', 'horaires_affiches' => false, 'horaires' => ['lundi' => '18 h – 22 h']]);

        $this->client->request('GET', '/informations');

        self::assertSelectorNotExists('.horaires');
        self::assertSame('18 h – 22 h', static::getContainer()->get(InformationsSalle::class)->lire()['horaires']['lundi']);
    }

    /** Adresse sans coordonnées : pas de carte intégrée, mais des liens de recherche de l'adresse. */
    public function testSansCoordonneesLaCarteChercheLAdresse(): void
    {
        $this->enregistrer(['adresse' => "12 route du Causse\n24370 Veyrignac"]);

        $page = $this->client->request('GET', '/informations');

        self::assertSelectorNotExists('iframe');
        self::assertStringContainsString('query=12%20route%20du%20Causse%2C%2024370%20Veyrignac',
            (string) $page->filter('.liens-carte a')->first()->attr('href'));
        self::assertSelectorNotExists('.caracteristiques');
    }

    /** @param array<string, mixed> $infos */
    private function enregistrer(array $infos): void
    {
        static::getContainer()->get(InformationsSalle::class)->enregistrer($infos + [
            'adresse' => '', 'coordonnees' => '', 'site_web' => '', 'horaires_affiches' => false,
            'horaires' => [], 'horaires_remarque' => '', 'hauteur' => null, 'surface' => null,
        ]);
    }
}
