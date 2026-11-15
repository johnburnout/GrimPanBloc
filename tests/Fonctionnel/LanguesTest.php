<?php

namespace App\Tests\Fonctionnel;

use App\Entity\User;
use App\Locale\Langues;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Langue de l'interface : celle du navigateur, puis celle choisie dans le pied de
 * page (cookie, et compte du grimpeur connecté). Toutes les pages existent dans
 * chaque langue, sans clé de traduction oubliée.
 */
final class LanguesTest extends FonctionnelTestCase
{
    public function testLaLangueDuNavigateurEstUtilisee(): void
    {
        $this->client->request('GET', '/secteurs', server: ['HTTP_ACCEPT_LANGUAGE' => 'es-ES,es;q=0.9,en;q=0.5']);

        self::assertSelectorExists('html[lang="es"]');
        self::assertSelectorTextContains('h1', 'Sectores');
        self::assertSelectorExists('.langues a[lang="es"][aria-current]');
    }

    public function testUneLangueNonProposeeRetombeSurLeFrancais(): void
    {
        $this->client->request('GET', '/secteurs', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9']);

        self::assertSelectorExists('html[lang="fr"]');
        self::assertSelectorTextContains('h1', 'Secteurs');
    }

    /** Le choix du pied de page l'emporte sur le navigateur, et ramène à la page d'origine. */
    public function testLeSelecteurMemoriseLaLangue(): void
    {
        $page = $this->client->request('GET', '/secteurs?vue=liste');
        $this->client->click($page->filter('.langues a[lang="ru"]')->link());

        self::assertResponseRedirects('/secteurs?vue=liste');
        $cookie = $this->client->getCookieJar()->get(Langues::COOKIE);
        self::assertSame('ru', $cookie?->getValue());
        self::assertTrue($cookie->isHttpOnly());

        $this->client->followRedirect();
        self::assertSelectorExists('html[lang="ru"]');
        self::assertSelectorTextContains('h1', 'Секторы');
    }

    public function testLeRetourNeMeneJamaisVersUnAutreSite(): void
    {
        foreach (['https://pirate.example/', '//pirate.example/', '/\\pirate.example/'] as $retour) {
            $this->client->request('GET', '/langue/en', ['retour' => $retour]);
            self::assertResponseRedirects('/', null, $retour);
        }
    }

    public function testUneLangueInconnueEstRefusee(): void
    {
        $this->client->request('GET', '/langue/de');

        self::assertResponseStatusCodeSame(404);
    }

    /** Connecté : la langue est enregistrée dans le compte et revient à la connexion suivante, sur un autre appareil. */
    public function testLaLangueDuCompteSuitLeGrimpeur(): void
    {
        $grimpeur = $this->creerUtilisateur('grimpeur');
        $this->client->loginUser($grimpeur);
        $this->client->request('GET', '/langue/it');

        $this->em->clear();
        self::assertSame('it', $this->em->find(User::class, $grimpeur->getId())?->getLangue());

        // Autre appareil : pas de cookie ; connexion par le formulaire
        $this->client->getCookieJar()->clear();
        $this->em->find(User::class, $grimpeur->getId())?->setPassword(
            static::getContainer()->get('security.user_password_hasher')->hashPassword($grimpeur, 'mot-de-passe-1'));
        $this->em->flush();
        $page = $this->client->request('GET', '/connexion');
        $this->client->submit($page->filter('form')->form(['_username' => 'grimpeur@exemple.test', '_password' => 'mot-de-passe-1']));

        self::assertSame('it', $this->client->getCookieJar()->get(Langues::COOKIE)?->getValue());
        $this->client->followRedirect();
        self::assertSelectorExists('html[lang="it"]');
    }

    /** Le mot de confirmation se tape dans la langue affichée (le français reste accepté). */
    public function testLeMotDeConfirmationEstTraduit(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $voie = $this->creerVoie($auteur);
        $this->valider($voie, $this->creerUtilisateur('grimpeur'));
        $id = $voie->getId();
        $this->choisir('en');
        $this->client->loginUser($auteur);

        $page = $this->client->request('GET', "/voies/$id");
        self::assertSelectorTextContains('#suppression label', 'Type DELETE to confirm');
        $jeton = $this->jeton($page, "/voies/$id/supprimer");

        $this->client->request('POST', "/voies/$id/supprimer", ['_token' => $jeton, 'confirmation' => 'SUPPRIMER-NON']);
        self::assertContains('Deletion cancelled: type DELETE to confirm.', $this->flashsApresRedirection('error'));

        $this->client->request('POST', "/voies/$id/supprimer", ['_token' => $jeton, 'confirmation' => 'DELETE']);
        self::assertFalse($this->voieExiste($id));
    }

    public function testLesMessagesDeValidationSontTraduits(): void
    {
        $this->choisir('ru');
        $page = $this->client->request('GET', '/inscription');
        $form = $page->filter('form')->form();
        $this->client->submit($form, [
            $form->getName().'[email]' => 'nouveau@exemple.test',
            $form->getName().'[pseudo]' => 'nouveau',
            $form->getName().'[plainPassword][first]' => 'court',
            $form->getName().'[plainPassword][second]' => 'court',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Не менее 8 символов.');
    }

    /** @return iterable<string, array{string}> */
    public static function langues(): iterable
    {
        foreach (['fr', 'en', 'es', 'it', 'ru'] as $langue) {
            yield $langue => [$langue];
        }
    }

    /** Chaque page, publique ou d'administration, s'affiche sans clé de traduction restée telle quelle. */
    #[\PHPUnit\Framework\Attributes\DataProvider('langues')]
    public function testToutesLesPagesSontTraduites(string $langue): void
    {
        $admin = $this->creerUtilisateur('admin', admin: true);
        $voie = $this->creerVoie($admin);
        $secteur = $voie->getPhoto()->getSecteur()->getId();
        $this->choisir($langue);

        $pages = ['/', '/?vue=liste', '/secteurs', "/secteurs/$secteur", '/informations', '/connexion', '/inscription'];
        foreach ($pages as $url) {
            $this->assertPageTraduite($url, $langue);
        }

        $this->client->loginUser($admin);
        $pages = ["/voies/{$voie->getId()}", "/voies/{$voie->getId()}/modifier", "/voies/{$voie->getId()}/dupliquer", "/voies/nouvelle/$secteur",
            '/profil', '/profil/croix', '/profil/voies', '/secteurs/nouveau',
            '/admin/reglages', '/admin/utilisateurs', '/admin/photos', '/admin/sauvegardes', '/admin/fiche', '/admin/mise-a-jour',
            "/admin/secteurs/$secteur/nettoyer", "/admin/secteurs/$secteur/supprimer"];
        foreach ($pages as $url) {
            $this->assertPageTraduite($url, $langue);
        }
    }

    // ------------------------------------------------------------------------

    private function choisir(string $langue): void
    {
        $this->client->getCookieJar()->set(new Cookie(Langues::COOKIE, $langue));
    }

    private function assertPageTraduite(string $url, string $langue): void
    {
        $page = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful("$url ($langue)");
        self::assertSame($langue, $page->filter('html')->attr('lang'), "$url ($langue)");

        // Une clé oubliée s'affiche telle quelle : « voie.form.nom », « reglages.smtp.titre »…
        $texte = $page->filter('body')->text().' '.implode(' ', $page->filter('[placeholder], [title], [aria-label], [alt]')->each(
            fn ($n) => $n->attr('placeholder').' '.$n->attr('title').' '.$n->attr('aria-label').' '.$n->attr('alt')));
        self::assertDoesNotMatchRegularExpression('/\b(?:accueil|admin|commun|confirmation|connexion|enum|erreur|fiche|flash|infos|inscription|jour|js|maj|nav|nettoyer|photos|pied|profil|reglages|restaurer|sauvegardes|secteurs?|supprimer_secteur|utilisateurs|voies?)\.[a-z_]+\b/', $texte, "Clé non traduite sur $url ($langue)");
    }
}
