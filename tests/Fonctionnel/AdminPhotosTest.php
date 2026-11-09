<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Photo;
use App\Entity\Prise;
use App\Entity\Secteur;
use App\Entity\User;
use App\Entity\Voie;
use Symfony\Component\Filesystem\Filesystem;

/** Administration → Photos : photos qu'aucune voie n'utilise, et leur suppression. */
final class AdminPhotosTest extends FonctionnelTestCase
{
    private User $admin;
    private string $dossier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->creerUtilisateur('admin', admin: true);
        $this->dossier = static::getContainer()->getParameter('photos_dir');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dossier);
        parent::tearDown();
    }

    public function testListeLesSeulesPhotosInutiliseesAvecLeurSecteur(): void
    {
        $utilisee = $this->creerVoie($this->admin)->getPhoto();
        $this->creerPhoto('Dévers', 'libre.jpg');

        $this->client->loginUser($this->admin);
        $page = $this->client->request('GET', '/admin/photos');

        self::assertResponseIsSuccessful();
        $cartes = $page->filter('.carte-photo');
        self::assertCount(1, $cartes);
        self::assertStringContainsString('Dévers', $cartes->text());
        self::assertStringEndsWith('/libre.jpg', (string) $cartes->filter('img')->attr('src'));
        self::assertStringNotContainsString($utilisee->getFichier(), $page->html());
    }

    public function testAucunePhotoInutilisee(): void
    {
        $this->creerVoie($this->admin);

        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/photos');

        self::assertSelectorTextContains('.vide', 'Aucune photo inutilisée.');
    }

    public function testSupprimerUnePhotoEtSonFichier(): void
    {
        $photo = $this->creerPhoto('Dévers', 'libre.jpg');
        $id = $photo->getId();

        $this->client->loginUser($this->admin);
        $this->client->submit($this->formulaireSuppression());

        self::assertResponseRedirects('/admin/photos');
        self::assertContains('Photo du secteur « Dévers » supprimée.', $this->flashsApresRedirection('success'));
        $this->em->clear();
        self::assertNull($this->em->find(Photo::class, $id));
        self::assertFileDoesNotExist($this->dossier.'/libre.jpg');
        self::assertSelectorTextContains('.vide', 'Aucune photo inutilisée.');
    }

    /** Une voie a repris la photo entre l'affichage de la page et le clic : refus. */
    public function testUnePhotoDevenueUtiliseeNEstPasSupprimee(): void
    {
        $photo = $this->creerPhoto('Dévers', 'libre.jpg');
        $this->client->loginUser($this->admin);
        $form = $this->formulaireSuppression();

        $voie = (new Voie())->setNom('Reprise')->setAuteur($this->admin)->setPhoto($photo);
        $voie->addPrise((new Prise())->setX(10)->setY(20));
        $this->em->persist($voie);
        $this->em->flush();
        $this->em->clear();

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/photos');
        self::assertSame(['Cette photo est utilisée par une voie : supprimez ou modifiez d\'abord la voie.'], $this->flashsApresRedirection('error'));
        self::assertNotNull($this->em->find(Photo::class, $photo->getId()));
        self::assertFileExists($this->dossier.'/libre.jpg');
    }

    public function testJetonInvalideRefuse(): void
    {
        $photo = $this->creerPhoto('Dévers', 'libre.jpg');

        $this->client->loginUser($this->admin);
        $this->client->request('POST', '/admin/photos/'.$photo->getId().'/supprimer', ['_token' => 'faux']);

        self::assertResponseStatusCodeSame(403);
        self::assertFileExists($this->dossier.'/libre.jpg');
    }

    public function testReserveeAuxAdministrateurs(): void
    {
        $photo = $this->creerPhoto('Dévers', 'libre.jpg');
        $this->client->loginUser($this->creerUtilisateur('grimpeur'));

        $this->client->request('GET', '/admin/photos');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/admin/photos/'.$photo->getId().'/supprimer', ['_token' => 'x']);
        self::assertResponseStatusCodeSame(403);
        self::assertFileExists($this->dossier.'/libre.jpg');
    }

    private function creerPhoto(string $secteurNom, string $fichier): Photo
    {
        (new Filesystem())->dumpFile($this->dossier.'/'.$fichier, 'image');
        $secteur = (new Secteur())->setNom($secteurNom);
        $photo = (new Photo())->setFichier($fichier)->setLargeur(800)->setHauteur(600)->setSecteur($secteur)->setAuteur($this->admin);
        $this->em->persist($secteur);
        $this->em->persist($photo);
        $this->em->flush();

        return $photo;
    }

    private function formulaireSuppression(): \Symfony\Component\DomCrawler\Form
    {
        $page = $this->client->request('GET', '/admin/photos');
        $form = $page->filter('.carte-photo form');
        self::assertCount(1, $form);

        return $form->form();
    }
}
