<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Photo;
use App\Entity\Secteur;
use App\Entity\User;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Page d'un secteur : un administrateur y envoie des photos pour les futures voies. */
final class SecteurPhotosTest extends FonctionnelTestCase
{
    private User $admin;
    private Secteur $secteur;
    private string $dossier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->creerUtilisateur('admin', admin: true);
        $this->secteur = (new Secteur())->setNom('Dévers');
        $this->em->persist($this->secteur);
        $this->em->flush();
        $this->dossier = static::getContainer()->getParameter('photos_dir');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dossier);
        parent::tearDown();
    }

    public function testUnAdminEnvoiePlusieursPhotosDepuisLaPageDuSecteur(): void
    {
        $this->client->loginUser($this->admin);
        $this->envoyer($this->imageJpeg(), $this->imageJpeg());

        $url = '/secteurs/'.$this->secteur->getId();
        self::assertResponseRedirects($url);
        self::assertContains('2 photos ajoutées au secteur « Dévers ».', $this->flashsApresRedirection('success'));

        $photos = $this->photosDuSecteur();
        self::assertCount(2, $photos);
        foreach ($photos as $photo) {
            self::assertSame($this->admin->getId(), $photo->getAuteur()->getId());
            self::assertTrue($photo->getVoies()->isEmpty());
            self::assertFileExists($this->dossier.'/'.$photo->getFichier());
        }
        self::assertSelectorTextContains('.ajout-photos .aide', 'Ce secteur compte 2 photos.');

        // Elles sont proposées à la création d'une voie dans ce secteur
        $page = $this->client->request('GET', '/voies/nouvelle/'.$this->secteur->getId());
        foreach ($photos as $photo) {
            self::assertCount(1, $page->filter(\sprintf('select[name="voie[photo]"] option[value="%d"]', $photo->getId())));
        }
    }

    public function testEnvoiSansPhotoRefuse(): void
    {
        $this->client->loginUser($this->admin);
        $this->envoyer();

        self::assertResponseRedirects('/secteurs/'.$this->secteur->getId());
        self::assertContains('Choisissez au moins une photo.', $this->flashsApresRedirection('error'));
        self::assertCount(0, $this->photosDuSecteur());
    }

    public function testPhotoTropGrandeRefuseeEtRienNEstAjoute(): void
    {
        $this->client->loginUser($this->admin);
        $this->envoyer($this->imageJpeg(), $this->pngGeant(10_000, 10_000));

        self::assertResponseRedirects();
        $erreurs = $this->flashsApresRedirection('error');
        self::assertCount(1, $erreurs);
        self::assertStringContainsString('La photo dépasse 25 mégapixels', $erreurs[0]);
        self::assertCount(0, $this->photosDuSecteur());
    }

    public function testJetonInvalideRefuse(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('POST', '/admin/secteurs/'.$this->secteur->getId().'/photos',
            ['photos_secteur' => ['_token' => 'faux']], ['photos_secteur' => ['photos' => [$this->imageJpeg()]]]);

        self::assertResponseRedirects();
        self::assertNotEmpty($this->flashsApresRedirection('error'));
        self::assertCount(0, $this->photosDuSecteur());
    }

    public function testReserveAuxAdministrateurs(): void
    {
        $url = '/secteurs/'.$this->secteur->getId();

        $this->client->request('GET', $url);
        self::assertSelectorNotExists('.ajout-photos');

        $this->client->loginUser($this->creerUtilisateur('grimpeur'));
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.ajout-photos');

        $this->client->request('POST', '/admin/secteurs/'.$this->secteur->getId().'/photos',
            [], ['photos_secteur' => ['photos' => [$this->imageJpeg()]]]);
        self::assertResponseStatusCodeSame(403);
        self::assertCount(0, $this->photosDuSecteur());
    }

    /**
     * Envoie les fichiers par le formulaire de la page du secteur (adresse et jeton
     * de la page), comme le ferait le navigateur avec un champ multiple.
     */
    private function envoyer(UploadedFile ...$fichiers): void
    {
        $page = $this->client->request('GET', '/secteurs/'.$this->secteur->getId());
        self::assertResponseIsSuccessful();
        $form = $page->filter('.ajout-photos form');
        self::assertCount(1, $form);
        self::assertSame('multipart/form-data', $form->attr('enctype'));
        self::assertCount(1, $form->filter('input[type="file"][name="photos_secteur[photos][]"][multiple]'));

        $this->client->request('POST', (string) $form->attr('action'),
            ['photos_secteur' => ['_token' => $form->filter('input[name="photos_secteur[_token]"]')->attr('value')]],
            ['photos_secteur' => ['photos' => $fichiers]]);
    }

    private function imageJpeg(): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'secteur').'.jpg';
        imagejpeg(imagecreatetruecolor(40, 30), $chemin);

        return new UploadedFile($chemin, 'mur.jpg', 'image/jpeg', null, true);
    }

    /** @return list<Photo> */
    private function photosDuSecteur(): array
    {
        $this->em->clear();

        return $this->em->getRepository(Photo::class)->findBy(['secteur' => $this->secteur->getId()]);
    }
}
