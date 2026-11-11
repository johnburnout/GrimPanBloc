<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Voie;
use App\Enum\TypePrise;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Création et modification d'une voie par le formulaire, comme dans le navigateur. */
final class VoieFormulaireTest extends FonctionnelTestCase
{
    protected function tearDown(): void
    {
        (new Filesystem())->remove(static::getContainer()->getParameter('photos_dir'));
        parent::tearDown();
    }

    // --- Création -----------------------------------------------------------

    public function testCreerUneVoieSurUnePhotoExistante(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $photo = $this->creerVoie($auteur)->getPhoto();
        $secteurId = $photo->getSecteur()->getId();

        $this->client->loginUser($auteur);
        $form = $this->formulaire("/voies/nouvelle/$secteurId");
        $form['voie[photo]'] = (string) $photo->getId();
        $form['voie[prises]'] = json_encode([
            ['x' => 10, 'y' => 20, 'r' => 4, 'type' => 'depart'],
            ['x' => 50, 'y' => 60],
        ], \JSON_THROW_ON_ERROR);
        $this->remplirInfos($form, 'Le toit');
        $this->client->submit($form);

        $voie = $this->derniereVoie();
        self::assertResponseRedirects('/voies/'.$voie->getId());
        self::assertContains('Voie « Le toit » créée.', $this->flashsApresRedirection('success'));
        self::assertSame($photo->getId(), $voie->getPhoto()->getId());
        self::assertSame('6b', $voie->getCotation());
        self::assertSame([
            ['x' => 10.0, 'y' => 20.0, 'r' => 4.0, 'type' => 'depart'],
            ['x' => 50.0, 'y' => 60.0, 'r' => 3.0, 'type' => 'main'],
        ], $voie->getPrisesArray());
    }

    public function testCreerUneVoieAvecUneNouvellePhoto(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $secteurId = $this->creerVoie($auteur)->getPhoto()->getSecteur()->getId();

        $this->client->loginUser($auteur);
        $form = $this->formulaire("/voies/nouvelle/$secteurId");
        $form['voie[photo]'] = '';
        $this->joindre($form, $this->imageJpeg());
        $form['voie[prises]'] = json_encode([['x' => 30, 'y' => 40, 'type' => 'top']], \JSON_THROW_ON_ERROR);
        $this->remplirInfos($form, 'Nouvelle photo');
        $this->client->submit($form);

        self::assertResponseRedirects();
        $voie = $this->derniereVoie();
        self::assertSame('Nouvelle photo', $voie->getNom());
        self::assertSame($secteurId, $voie->getPhoto()->getSecteur()->getId());
        self::assertSame($auteur->getId(), $voie->getPhoto()->getAuteur()->getId());
        self::assertFileExists(static::getContainer()->getParameter('photos_dir').'/'.$voie->getPhoto()->getFichier());
    }

    public function testCreationRefuseeSansPhotoNiPrise(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $secteurId = $this->creerVoie($auteur)->getPhoto()->getSecteur()->getId();
        $avant = $this->nombreDeVoies();

        $this->client->loginUser($auteur);
        $form = $this->formulaire("/voies/nouvelle/$secteurId");
        $form['voie[photo]'] = '';
        $form['voie[prises]'] = 'pas du json';
        $this->remplirInfos($form, 'Incomplète');
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('form', 'Envoyez une photo ou choisissez-en une existante.');
        self::assertAnySelectorTextContains('form', 'Marquez au moins une prise sur la photo.');
        self::assertSame($avant, $this->nombreDeVoies());
    }

    public function testPhotoTropGrandeRefuseeSansEtreDecodee(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $secteurId = $this->creerVoie($auteur)->getPhoto()->getSecteur()->getId();
        $avant = $this->nombreDeVoies();

        $this->client->loginUser($auteur);
        $form = $this->formulaire("/voies/nouvelle/$secteurId");
        $form['voie[photo]'] = '';
        $this->joindre($form, $this->pngGeant(10_000, 10_000));
        $form['voie[prises]'] = json_encode([['x' => 30, 'y' => 40]], \JSON_THROW_ON_ERROR);
        $this->remplirInfos($form, 'Bombe');
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('form', 'La photo dépasse 25 mégapixels');
        self::assertSame($avant, $this->nombreDeVoies());
    }

    public function testLesPrisesInvalidesSontIgnoreesEtLeNombreEstLimite(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $photo = $this->creerVoie($auteur)->getPhoto();

        $prises = [['x' => 'a', 'y' => 1], 'texte', ['x' => 1], ['x' => 5, 'y' => 5, 'type' => 'inconnu', 'r' => 'gros']];
        for ($i = 0; $i < 250; ++$i) {
            $prises[] = ['x' => 1, 'y' => 2];
        }

        $this->client->loginUser($auteur);
        $form = $this->formulaire('/voies/nouvelle/'.$photo->getSecteur()->getId());
        $form['voie[photo]'] = (string) $photo->getId();
        $form['voie[prises]'] = json_encode($prises, \JSON_THROW_ON_ERROR);
        $this->remplirInfos($form, 'Beaucoup de prises');
        $this->client->submit($form);

        self::assertResponseRedirects();
        $voie = $this->derniereVoie();
        // 200 entrées lues au plus, dont 3 invalides ignorées
        self::assertCount(197, $voie->getPrises());
        $premiere = $voie->getPrisesArray()[0];
        self::assertSame(['x' => 5.0, 'y' => 5.0, 'r' => 3.0, 'type' => TypePrise::Main->value], $premiere);
    }

    // --- Modification -------------------------------------------------------

    public function testModifierLesPrisesDUneVoieNonValidee(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $id = $this->creerVoie($auteur)->getId();

        $this->client->loginUser($auteur);
        $form = $this->formulaire("/voies/$id/modifier");
        self::assertSame([['x' => 10, 'y' => 20, 'r' => 3, 'type' => 'main']], json_decode($form['voie[prises]']->getValue(), true));

        $form['voie[prises]'] = json_encode([['x' => 70, 'y' => 80, 'type' => 'pied'], ['x' => 1, 'y' => 1]], \JSON_THROW_ON_ERROR);
        $form['voie[nom]'] = 'Renommée';
        $this->client->submit($form);

        self::assertResponseRedirects("/voies/$id");
        self::assertContains('Voie « Renommée » modifiée.', $this->flashsApresRedirection('success'));
        $this->em->clear();
        $voie = $this->em->find(Voie::class, $id);
        self::assertSame('Renommée', $voie->getNom());
        self::assertSame([
            ['x' => 70.0, 'y' => 80.0, 'r' => 3.0, 'type' => 'pied'],
            ['x' => 1.0, 'y' => 1.0, 'r' => 3.0, 'type' => 'main'],
        ], $voie->getPrisesArray());
    }

    public function testModificationRefuseeSiToutesLesPrisesSontEffacees(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $id = $this->creerVoie($auteur)->getId();

        $this->client->loginUser($auteur);
        $form = $this->formulaire("/voies/$id/modifier");
        $form['voie[prises]'] = '[]';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('form', 'Marquez au moins une prise sur la photo.');
        $this->em->clear();
        self::assertCount(1, $this->em->find(Voie::class, $id)->getPrises());
    }

    public function testUneVoieValideeNeModifieQueLeNomEtLaDescription(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $voie = $this->creerVoie($auteur);
        $this->valider($voie, $this->creerUtilisateur('grimpeur'));
        $id = $voie->getId();

        $this->client->loginUser($auteur);
        $form = $this->formulaire("/voies/$id/modifier");
        self::assertFalse($form->has('voie[prises]'));
        self::assertFalse($form->has('voie[photo]'));

        $form['voie[nom]'] = 'Nom corrigé';
        $form['voie[description]'] = 'Départ assis';
        $this->client->submit($form);

        self::assertResponseRedirects("/voies/$id");
        $this->em->clear();
        $voie = $this->em->find(Voie::class, $id);
        self::assertSame('Nom corrigé', $voie->getNom());
        self::assertSame('Départ assis', $voie->getDescription());
        self::assertCount(1, $voie->getPrises());
    }

    public function testUnAutreGrimpeurNePeutPasModifier(): void
    {
        $id = $this->creerVoie($this->creerUtilisateur('auteur'))->getId();

        $this->client->loginUser($this->creerUtilisateur('intrus'));
        $this->client->request('GET', "/voies/$id/modifier");

        self::assertResponseStatusCodeSame(403);
    }

    // --- Éditeur -------------------------------------------------------------

    public function testLEditeurProposeBarreFlottanteZoomEtPleinEcran(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $id = $this->creerVoie($auteur)->getId();

        $this->client->loginUser($auteur);
        $page = $this->client->request('GET', "/voies/$id/modifier");
        self::assertResponseIsSuccessful();

        // Barre d'outils posée sur la photo, avec poignée et un bouton par type de prise
        $barre = $page->filter('#vue #barre-outils[role="toolbar"]');
        self::assertCount(1, $barre);
        self::assertCount(1, $barre->filter('#poignee'));
        foreach (TypePrise::cases() as $type) {
            self::assertCount(1, $barre->filter(\sprintf('[data-outil="%s"]', $type->value)));
        }
        self::assertCount(1, $barre->filter('[data-outil="gomme"]'));
        self::assertCount(1, $barre->filter('#taille-prise'));
        self::assertCount(1, $barre->filter('#annuler'));

        // Curseur de zoom sur le côté et plein écran
        self::assertCount(1, $page->filter('#vue .zoom #zoom-curseur[type="range"]'));
        self::assertCount(3, $page->filter('#vue .zoom [data-zoom]'));
        self::assertCount(1, $page->filter('#vue #plein-ecran'));

        self::assertCount(1, $page->filter('script[src*="js/editeur.js"]'));
    }

    // ------------------------------------------------------------------------

    private function formulaire(string $url): Form
    {
        $page = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        return $page->filter('form[name="voie"]')->form();
    }

    private function remplirInfos(Form $form, string $nom): void
    {
        $form['voie[nom]'] = $nom;
        $form['voie[systeme]'] = 'fr';
        $form['voie[cotationFr]'] = '6b';
    }

    /** Joint le fichier au champ « Nouvelle photo », comme le ferait le navigateur. */
    private function joindre(Form $form, UploadedFile $fichier): void
    {
        $champ = $form['voie[nouvellePhoto]'];
        self::assertInstanceOf(FileFormField::class, $champ);
        $champ->upload($fichier->getPathname());
    }

    private function imageJpeg(): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'voie').'.jpg';
        $image = imagecreatetruecolor(40, 30);
        imagejpeg($image, $chemin);

        return new UploadedFile($chemin, 'mur.jpg', 'image/jpeg', null, true);
    }

    private function derniereVoie(): Voie
    {
        $this->em->clear();

        return $this->em->getRepository(Voie::class)->findOneBy([], ['id' => 'DESC']);
    }

    private function nombreDeVoies(): int
    {
        return $this->em->getRepository(Voie::class)->count();
    }
}
