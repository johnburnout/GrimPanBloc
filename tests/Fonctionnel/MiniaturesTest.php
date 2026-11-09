<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Photo;
use App\Entity\User;
use App\Service\GestionPhotos;
use App\Service\MiniaturesPhotos;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\Filesystem\Filesystem;

/** Miniatures des photos : création à l'envoi, affichage dans les listes, rattrapage, suppression. */
final class MiniaturesTest extends FonctionnelTestCase
{
    private string $dossier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dossier = static::getContainer()->getParameter('photos_dir');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dossier);
        parent::tearDown();
    }

    public function testUneNouvellePhotoAUneMiniatureUtiliseeDansLesListes(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $secteurId = $this->creerVoie($auteur)->getPhoto()->getSecteur()->getId();
        $this->client->loginUser($auteur);

        $form = $this->client->request('GET', "/voies/nouvelle/$secteurId")->filter('form[name="voie"]')->form();
        $form['voie[photo]'] = '';
        $champ = $form['voie[nouvellePhoto]'];
        self::assertInstanceOf(FileFormField::class, $champ);
        $champ->upload($this->jpeg(1200, 900));
        $form['voie[prises]'] = json_encode([['x' => 30, 'y' => 40]], \JSON_THROW_ON_ERROR);
        $form['voie[nom]'] = 'Grande photo';
        $form['voie[systeme]'] = 'fr';
        $form['voie[cotationFr]'] = '6b';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->em->clear();
        $photo = $this->em->getRepository(Photo::class)->findOneBy([], ['id' => 'DESC']);
        $miniature = $this->dossier.'/miniatures/'.$photo->getFichier();
        self::assertFileExists($miniature);
        self::assertSame([800, 600], \array_slice(getimagesize($miniature), 0, 2));

        // Liste des voies : la miniature ; page de la voie : la photo entière
        $liste = $this->client->request('GET', '/');
        $carte = $liste->filter('.carte-voie')->reduce(fn ($c) => str_contains($c->text(), 'Grande photo'));
        self::assertCount(1, $carte);
        self::assertSame('/uploads/photos/miniatures/'.$photo->getFichier(), $carte->filter('img')->attr('src'));
        $voie = $this->client->request('GET', '/voies/'.$photo->getVoies()->first()->getId());
        self::assertSame('/uploads/photos/'.$photo->getFichier(), $voie->filter('.photo-voie img')->attr('src'));
    }

    public function testSansMiniatureLaPhotoEntiereSert(): void
    {
        $photo = $this->creerPhoto($this->creerUtilisateur('auteur'), 'petite.jpg', 400, 300);
        $miniatures = static::getContainer()->get(MiniaturesPhotos::class);

        self::assertFalse($miniatures->creer('petite.jpg'), 'Déjà assez petite : pas de miniature.');
        self::assertSame('uploads/photos/petite.jpg', $miniatures->chemin($photo));
    }

    public function testLaCommandeCreeLesMiniaturesManquantes(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $this->creerPhoto($auteur, 'ancienne.jpg', 1000, 2000);
        $this->creerPhoto($auteur, 'petite.jpg', 400, 300);

        $commande = new CommandTester((new Application(static::$kernel))->find('app:photos:miniatures'));
        self::assertSame(0, $commande->execute([]));
        self::assertStringContainsString('1 miniature(s) créée(s)', $commande->getDisplay());
        self::assertSame([400, 800], \array_slice(getimagesize($this->dossier.'/miniatures/ancienne.jpg'), 0, 2));

        self::assertSame(0, $commande->execute([]));
        self::assertStringContainsString('Aucune miniature à créer', $commande->getDisplay());
    }

    public function testSupprimerUnePhotoSupprimeSaMiniature(): void
    {
        $photo = $this->creerPhoto($this->creerUtilisateur('auteur'), 'libre.jpg', 1200, 900);
        static::getContainer()->get(MiniaturesPhotos::class)->creer('libre.jpg');
        self::assertFileExists($this->dossier.'/miniatures/libre.jpg');

        static::getContainer()->get(GestionPhotos::class)->supprimer($photo);

        self::assertFileDoesNotExist($this->dossier.'/libre.jpg');
        self::assertFileDoesNotExist($this->dossier.'/miniatures/libre.jpg');
    }

    public function testLaPageAdminPhotosAfficheLaMiniature(): void
    {
        $admin = $this->creerUtilisateur('admin', admin: true);
        $this->creerPhoto($admin, 'libre.jpg', 1200, 900);
        static::getContainer()->get(MiniaturesPhotos::class)->creer('libre.jpg');
        $this->client->loginUser($admin);

        $page = $this->client->request('GET', '/admin/photos');

        self::assertSame('/uploads/photos/miniatures/libre.jpg', $page->filter('.carte-photo img')->attr('src'));
        self::assertSame('/uploads/photos/libre.jpg', $page->filter('.carte-photo .cadre-photo a')->attr('href'));
    }

    private function creerPhoto(User $auteur, string $fichier, int $largeur, int $hauteur): Photo
    {
        (new Filesystem())->mkdir($this->dossier);
        imagejpeg(imagecreatetruecolor($largeur, $hauteur), $this->dossier.'/'.$fichier);
        $voie = $this->creerVoie($auteur);
        $photo = (new Photo())->setFichier($fichier)->setLargeur($largeur)->setHauteur($hauteur)
            ->setSecteur($voie->getPhoto()->getSecteur())->setAuteur($auteur);
        $this->em->persist($photo);
        $this->em->flush();

        return $photo;
    }

    private function jpeg(int $largeur, int $hauteur): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'photo').'.jpg';
        imagejpeg(imagecreatetruecolor($largeur, $hauteur), $chemin);

        return $chemin;
    }
}
