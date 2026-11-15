<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Prise;
use App\Entity\Voie;
use App\Enum\SystemeCotation;
use App\Enum\TypePrise;
use Symfony\Component\DomCrawler\Crawler;

/** Pieds libres / pieds imposés, et duplication d'une voie depuis la page de son secteur. */
final class PiedsEtDuplicationTest extends FonctionnelTestCase
{
    public function testPiedsLibresMasquentLesPrisesDePiedSansLesEffacer(): void
    {
        $voie = $this->voieAvecUnPied($this->creerUtilisateur('auteur'));

        $page = $this->client->request('GET', '/voies/'.$voie->getId());
        self::assertCount(1, $page->filter('.photo-voie circle.prise-pied'), 'pieds imposés : le pied est dessiné');
        self::assertStringContainsString('pieds imposés', $page->filter('.meta')->text());

        $voie->setPiedsLibres(true);
        $this->em->flush();

        $page = $this->client->request('GET', '/voies/'.$voie->getId());
        self::assertCount(0, $page->filter('.photo-voie circle.prise-pied'), 'pieds libres : plus de cercle de pied');
        self::assertCount(1, $page->filter('.photo-voie circle.prise-main'));
        self::assertCount(0, $page->filter('.legende .prise-pied'));
        self::assertStringContainsString('pieds libres', $page->filter('.meta')->text());
        self::assertStringContainsString('· 1 prise', $page->filter('.meta')->text());

        // Les listes (miniatures) ne les dessinent pas non plus
        $page = $this->client->request('GET', '/');
        self::assertCount(0, $page->filter('.carte-voie circle.prise-pied'));

        $this->em->clear();
        self::assertCount(2, $this->em->find(Voie::class, $voie->getId())->getPrises(), 'le pied reste enregistré');
    }

    public function testLAuteurChoisitPiedsLibresDansLeFormulaire(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $voie = $this->voieAvecUnPied($auteur);
        $this->client->loginUser($auteur);

        $page = $this->client->request('GET', '/voies/'.$voie->getId().'/modifier');
        self::assertSame(['Pieds imposés', 'Pieds libres'], $page->filter('#voie_piedsLibres label')->each(fn (Crawler $l) => $l->text()));
        $form = $page->filter('form[name="voie"]')->form();
        $form['voie[piedsLibres]'] = '1';
        $this->client->submit($form);

        self::assertResponseRedirects('/voies/'.$voie->getId());
        $this->em->clear();
        $voie = $this->em->find(Voie::class, $voie->getId());
        self::assertTrue($voie->isPiedsLibres());
        self::assertCount(2, $voie->getPrises());
    }

    public function testUnGrimpeurDupliqueUneVoieDepuisLaPageDuSecteur(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $source = $this->voieAvecUnPied($auteur)->setPiedsLibres(true)->setDescription('Sans le bac');
        $this->em->flush();
        $this->valider($source, $this->creerUtilisateur('autre'), '6c');
        $secteurId = $source->getPhoto()->getSecteur()->getId();
        $grimpeur = $this->creerUtilisateur('grimpeur');
        $this->client->loginUser($grimpeur);

        foreach (['icones', 'liste'] as $vue) {
            $page = $this->client->request('GET', "/secteurs/$secteurId?vue=$vue");
            self::assertCount(1, $page->filter('a.lien-dupliquer'), $vue);
        }
        $page = $this->client->click($page->filter('a.lien-dupliquer')->link());

        self::assertResponseIsSuccessful();
        self::assertSame('Copie de « Dalle grise »', $page->filter('h1')->text());
        $form = $page->filter('form[name="voie"]')->form();
        self::assertSame('Dalle grise (copie)', $form['voie[nom]']->getValue());
        self::assertSame((string) $source->getPhoto()->getId(), $form['voie[photo]']->getValue());
        self::assertCount(2, json_decode((string) $form['voie[prises]']->getValue(), true, flags: \JSON_THROW_ON_ERROR));
        self::assertSame('1', $form['voie[piedsLibres]']->getValue());
        self::assertSame('6a', $form['voie[cotationFr]']->getValue(), 'la cotation proposée par l\'auteur, pas le consensus');

        $avant = $this->em->getRepository(Voie::class)->count();
        $form['voie[nom]'] = 'Dalle grise, variante';
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertSame($avant + 1, $this->em->getRepository(Voie::class)->count());
        $copie = $this->em->getRepository(Voie::class)->findOneBy(['nom' => 'Dalle grise, variante']);
        self::assertSame('grimpeur', $copie->getAuteur()->getPseudo());
        self::assertSame($source->getPhoto()->getId(), $copie->getPhoto()->getId(), 'même photo, pas de nouveau fichier');
        self::assertCount(2, $copie->getPrises());
        self::assertTrue($copie->isPiedsLibres());
        self::assertSame('Sans le bac', $copie->getDescription());
        self::assertSame(SystemeCotation::Francaise, $copie->getSysteme());
        self::assertSame('6a', $copie->getCotation());
        self::assertCount(0, $copie->getCroix(), 'les croix ne suivent pas');

        $original = $this->em->find(Voie::class, $source->getId());
        self::assertSame('Dalle grise', $original->getNom());
        self::assertCount(2, $original->getPrises());
        self::assertCount(1, $original->getCroix());
    }

    /** Seul le créateur modifie sa voie (pas même un admin) ; tout grimpeur connecté peut la dupliquer. */
    public function testSeulLeCreateurModifieMaisTousDupliquent(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $voie = $this->voieAvecUnPied($auteur);
        $id = $voie->getId();

        foreach ([$this->creerUtilisateur('grimpeur'), $this->creerUtilisateur('admin', admin: true)] as $autre) {
            $this->client->loginUser($autre);
            $page = $this->client->request('GET', "/voies/$id");
            self::assertCount(0, $page->selectLink('Modifier'), $autre->getPseudo());
            self::assertCount(1, $page->selectLink('Dupliquer'), $autre->getPseudo());
            $this->client->request('GET', "/voies/$id/modifier");
            self::assertResponseStatusCodeSame(403, $autre->getPseudo());
            $this->client->request('POST', "/voies/$id/modifier", ['voie' => ['nom' => 'Pirate']]);
            self::assertResponseStatusCodeSame(403, $autre->getPseudo());
            $this->client->request('GET', "/voies/$id/dupliquer");
            self::assertResponseIsSuccessful($autre->getPseudo());
        }

        $this->client->loginUser($auteur);
        $page = $this->client->request('GET', "/voies/$id");
        self::assertCount(1, $page->selectLink('Modifier'));
        $this->client->request('GET', "/voies/$id/modifier");
        self::assertResponseIsSuccessful();

        $this->em->clear();
        self::assertSame('Dalle grise', $this->em->find(Voie::class, $id)->getNom());
    }

    public function testUnVisiteurAnonymeNePeutPasDupliquer(): void
    {
        $voie = $this->voieAvecUnPied($this->creerUtilisateur('auteur'));
        $secteurId = $voie->getPhoto()->getSecteur()->getId();

        foreach (['icones', 'liste'] as $vue) {
            $page = $this->client->request('GET', "/secteurs/$secteurId?vue=$vue");
            self::assertCount(0, $page->filter('a.lien-dupliquer'), $vue);
        }
        $this->client->request('GET', '/voies/'.$voie->getId().'/dupliquer');
        self::assertResponseRedirects('/connexion');
    }

    public function testPasDeDuplicationDepuisLaListeGenerale(): void
    {
        $this->voieAvecUnPied($this->creerUtilisateur('auteur'));
        $this->client->loginUser($this->creerUtilisateur('grimpeur'));

        $page = $this->client->request('GET', '/?vue=liste');
        self::assertCount(0, $page->filter('a.lien-dupliquer'));
    }

    private function voieAvecUnPied(\App\Entity\User $auteur): Voie
    {
        $voie = $this->creerVoie($auteur);
        $voie->addPrise((new Prise())->setX(50)->setY(80)->setType(TypePrise::Pied));
        $this->em->flush();

        return $voie;
    }
}
