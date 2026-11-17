<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Photo;
use App\Entity\Prise;
use App\Entity\Recalage;
use App\Entity\Secteur;
use App\Entity\User;
use App\Entity\Voie;
use App\Enum\CotationFr;
use App\Locale\Langues;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Changement de photo d'une voie (page « Changer de photo », pour toute voie, validée
 * ou non) : les prises sont seulement replacées, les positions avant et après sont
 * enregistrées (Recalage), et la nouvelle photo est proposée sur l'accueil aux
 * créateurs des autres voies de l'ancienne photo, prises replacées.
 */
final class ChangementPhotoTest extends FonctionnelTestCase
{
    private Secteur $secteur;
    private Photo $ancienne;
    private Photo $nouvelle;

    protected function setUp(): void
    {
        parent::setUp();
        $proprietaire = $this->creerUtilisateur('photographe');
        $this->secteur = (new Secteur())->setNom('Pan');
        $this->em->persist($this->secteur);
        $this->ancienne = $this->photo($proprietaire, 'ancienne.jpg');
        $this->nouvelle = $this->photo($proprietaire, 'nouvelle.jpg');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove((string) static::getContainer()->getParameter('photos_dir'));
        parent::tearDown();
    }

    public function testChangerLaPhotoEnregistreLesDeplacementsDesPrises(): void
    {
        $alice = $this->creerUtilisateur('alice');
        $voie = $this->voie($alice, 'Traversée', [[10, 10], [90, 10], [10, 90], [90, 90], [50, 50]]);
        $this->voie($this->creerUtilisateur('bob'), 'Dièdre', [[30, 30]]);
        $validee = $this->voie($this->creerUtilisateur('claire'), 'Arête', [[40, 40]]);
        $this->valider($validee, $this->creerUtilisateur('dan'));

        $this->client->loginUser($alice);
        $form = $this->formulaire($voie);
        $this->deplacer($form, $this->nouvelle, fn (array $p) => ['x' => $p['x'] + 5, 'y' => $p['y']]);
        $this->client->submit($form);

        self::assertResponseRedirects('/voies/'.$voie->getId());
        // Bob et Claire : une voie validée peut aussi passer sur la nouvelle photo
        self::assertSame(['La nouvelle photo sera proposée pour les 2 autres voies de l\'ancienne photo.'], $this->flashsApresRedirection('info'));

        $recalage = $this->recalage();
        self::assertSame($this->ancienne->getId(), $recalage->getPhotoSource()->getId());
        self::assertSame($this->nouvelle->getId(), $recalage->getPhotoCible()->getId());
        self::assertCount(5, $recalage->getPaires());
        self::assertEquals(['voie' => $voie->getId(), 's' => [10, 10], 'c' => [15, 10]], $recalage->getPaires()[0]);
        // La matrice enregistrée est la translation de 5 % vers la droite
        self::assertEqualsWithDelta([1, 0, 5, 0, 1, 0, 0, 0, 1], $recalage->getMatrice(), 1e-6);
    }

    public function testModifierNeProposePlusDeChangerLaPhotoQueParSaPage(): void
    {
        $alice = $this->creerUtilisateur('alice');
        $voie = $this->voie($alice, 'Traversée', [[10, 10]]);
        $validee = $this->voie($alice, 'Arête', [[40, 40]]);
        $this->valider($validee, $this->creerUtilisateur('dan'));

        $this->client->loginUser($alice);
        foreach ([$voie, $validee] as $v) {
            $page = $this->client->request('GET', '/voies/'.$v->getId().'/modifier');
            self::assertCount(0, $page->filter('[name="voie[photo]"], [name="voie[nouvellePhoto]"]'), $v->getNom());
            self::assertSame('/voies/'.$v->getId().'/photo', $page->filter('a.btn')->reduce(fn ($a) => 'Changer de photo' === $a->text())->attr('href'), $v->getNom());
        }

        // La modification refuse une photo envoyée quand même (champ en trop)
        $form = $this->client->request('GET', '/voies/'.$voie->getId().'/modifier')->filter('form[name="voie"]')->form();
        $this->client->request('POST', '/voies/'.$voie->getId().'/modifier', ['voie' => ['photo' => $this->nouvelle->getId()] + $form->getPhpValues()['voie']]);
        self::assertResponseStatusCodeSame(422);
        $this->em->clear();
        self::assertSame($this->ancienne->getId(), $this->em->find(Voie::class, $voie->getId())->getPhoto()->getId());
    }

    public function testChangerDePhotoNeSupprimeNiNAjouteDePrise(): void
    {
        $alice = $this->creerUtilisateur('alice');
        $voie = $this->voie($alice, 'Traversée', [[10, 10], [50, 50]]);
        [$p1, $p2] = [$voie->getPrises()[0]->getId(), $voie->getPrises()[1]->getId()];
        $autre = $this->voie($this->creerUtilisateur('bob'), 'Dièdre', [[30, 30]]);

        $this->client->loginUser($alice);
        $page = $this->client->request('GET', '/voies/'.$voie->getId().'/photo');
        self::assertCount(1, $page->filter('#editeur[data-positions-seules]'));
        self::assertCount(0, $page->filter('[data-outil]'), 'ni types ni gomme');
        $form = $page->filter('form[name="voie"]')->form();
        $form['voie[photo]'] = (string) $this->nouvelle->getId();
        // Seconde prise « supprimée », première changée de type, une prise ajoutée, une prise d'une autre voie
        $form['voie[prises]'] = json_encode([
            ['x' => 12, 'y' => 14, 'r' => 4, 'type' => 'pied', 'o' => $p1],
            ['x' => 20, 'y' => 20, 'type' => 'main'],
            ['x' => 60, 'y' => 60, 'type' => 'main', 'o' => $autre->getPrises()->first()->getId()],
        ], \JSON_THROW_ON_ERROR);
        $this->client->submit($form);

        self::assertResponseRedirects('/voies/'.$voie->getId());
        $this->em->clear();
        $voie = $this->em->find(Voie::class, $voie->getId());
        self::assertSame($this->nouvelle->getId(), $voie->getPhoto()->getId());
        self::assertSame([[$p1, 12.0, 14.0, 4.0, 'main'], [$p2, 50.0, 50.0, 3.0, 'main']], array_map(
            fn (Prise $p) => [$p->getId(), $p->getX(), $p->getY(), $p->getRayon(), $p->getType()->value],
            $voie->getPrises()->toArray(),
        ), 'mêmes prises ; la prise non renvoyée garde sa position');
    }

    public function testIlFautChoisirUneAutrePhoto(): void
    {
        $alice = $this->creerUtilisateur('alice');
        $voie = $this->voie($alice, 'Traversée', [[10, 10]]);

        $this->client->loginUser($alice);
        $form = $this->formulaire($voie);
        self::assertSame((string) $this->ancienne->getId(), $form['voie[photo]']->getValue(), 'la photo actuelle est choisie au départ');
        $page = $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Choisissez une autre photo que celle de la voie', $page->filter('main')->text());
        self::assertSame(0, $this->em->getRepository(Recalage::class)->count([]));
    }

    public function testOnPeutEnvoyerUneNouvellePhoto(): void
    {
        $alice = $this->creerUtilisateur('alice');
        $voie = $this->voie($alice, 'Traversée', [[10, 10], [50, 50]]);

        $this->client->loginUser($alice);
        $form = $this->formulaire($voie);
        $form['voie[photo]'] = '';
        $champ = $form['voie[nouvellePhoto]'];
        self::assertInstanceOf(FileFormField::class, $champ);
        $chemin = tempnam(sys_get_temp_dir(), 'mur').'.jpg';
        imagejpeg(imagecreatetruecolor(80, 60), $chemin);
        $champ->upload($chemin);
        $this->client->submit($form);

        self::assertResponseRedirects('/voies/'.$voie->getId());
        $recalage = $this->recalage();
        self::assertNotSame($this->nouvelle->getId(), $recalage->getPhotoCible()->getId());
        self::assertSame($this->secteur->getId(), $recalage->getPhotoCible()->getSecteur()->getId());
        self::assertSame($recalage->getPhotoCible()->getId(), $this->em->find(Voie::class, $voie->getId())->getPhoto()->getId());
        self::assertCount(2, $recalage->getPaires());
    }

    public function testLaNouvellePhotoEstProposeeSurLAccueilAuxAutresCreateurs(): void
    {
        [$alice, $bob, $claire] = [$this->creerUtilisateur('alice'), $this->creerUtilisateur('bob'), $this->creerUtilisateur('claire')];
        $traversee = $this->voie($alice, 'Traversée', [[10, 10], [90, 10], [50, 90]]);
        $dievre = $this->voie($bob, 'Dièdre', [[30, 30], [70, 60]]);
        $this->valider($this->voie($claire, 'Arête', [[40, 40]]), $alice);
        $this->changerDePhoto($traversee);

        $this->client->loginUser($bob);
        $page = $this->client->request('GET', '/');
        $alertes = $page->filter('.alerte-photo');
        self::assertCount(1, $alertes);
        self::assertStringContainsString('Une nouvelle photo a été proposée pour votre voie « Dièdre »', $alertes->text());
        self::assertSame(
            '/voies/'.$dievre->getId().'/nouvelle-photo/'.$this->recalage()->getId(),
            $alertes->filter('a')->attr('href'),
        );

        // Aussi pour une voie validée ; ni pour la voie déjà passée sur la nouvelle photo, ni sans être connecté
        $this->client->loginUser($claire);
        self::assertStringContainsString('« Arête »', $this->client->request('GET', '/')->filter('.alerte-photo')->text());
        $this->client->loginUser($alice);
        self::assertCount(0, $this->client->request('GET', '/')->filter('.alerte-photo'));
        $this->client->getCookieJar()->clear(); // déconnecté
        self::assertCount(0, $this->client->request('GET', '/')->filter('.alerte-photo'));
    }

    public function testLaPropositionMontreLaVoieSurLesDeuxPhotos(): void
    {
        $bob = $this->creerUtilisateur('bob');
        $dievre = $this->voie($bob, 'Dièdre', [[30, 30]]);
        $this->changerDePhoto($this->voie($this->creerUtilisateur('alice'), 'Traversée', [[10, 10], [90, 10], [50, 90]]));
        $id = $this->recalage()->getId();

        $this->client->loginUser($bob);
        $page = $this->client->request('GET', '/voies/'.$dievre->getId()."/nouvelle-photo/$id");

        self::assertResponseIsSuccessful();
        $photos = $page->filter('.comparaison-photos .photo-voie');
        self::assertCount(2, $photos);
        self::assertStringEndsWith('ancienne.jpg', $photos->eq(0)->filter('img')->attr('src'));
        self::assertStringEndsWith('nouvelle.jpg', $photos->eq(1)->filter('img')->attr('src'));
        // 30 % → 35 % de 800 px
        self::assertSame('240', $photos->eq(0)->filter('circle.prise')->attr('cx'));
        self::assertEqualsWithDelta(280, (float) $photos->eq(1)->filter('circle.prise')->attr('cx'), 0.01);
        self::assertSame('/voies/'.$dievre->getId()."/photo?proposition=$id", $page->filter('.actions a.btn')->attr('href'));
    }

    public function testAdopterLaNouvellePhotoAffineLeRecalage(): void
    {
        $bob = $this->creerUtilisateur('bob');
        $dievre = $this->voie($bob, 'Dièdre', [[30, 30], [70, 60]]);
        $this->changerDePhoto($this->voie($this->creerUtilisateur('alice'), 'Traversée', [[10, 10], [90, 10], [50, 90]]));
        $recalage = $this->recalage();

        $this->client->loginUser($bob);
        $page = $this->client->request('GET', '/voies/'.$dievre->getId().'/photo?proposition='.$recalage->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('les prises y ont été replacées automatiquement', $page->filter('main')->text());
        $form = $page->filter('form[name="voie"]')->form();
        self::assertSame((string) $this->nouvelle->getId(), $form['voie[photo]']->getValue(), 'la nouvelle photo est choisie');
        $prises = json_decode((string) $form['voie[prises]']->getValue(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertEqualsWithDelta([35, 30], [$prises[0]['x'], $prises[0]['y']], 0.01, 'prise replacée');
        self::assertSame($dievre->getPrises()->first()->getId(), $prises[0]['o']);

        // Bob corrige une prise d'un pour cent vers le bas, puis enregistre
        ++$prises[1]['y'];
        $form['voie[prises]'] = json_encode($prises, \JSON_THROW_ON_ERROR);
        $this->client->submit($form);
        self::assertResponseRedirects('/voies/'.$dievre->getId());
        self::assertSame(['La voie « Dièdre » est passée sur la nouvelle photo.'], $this->flashsApresRedirection('success'));

        $this->em->clear();
        self::assertSame($this->nouvelle->getId(), $this->em->find(Voie::class, $dievre->getId())->getPhoto()->getId());
        $paires = $this->recalage()->getPaires();
        self::assertCount(5, $paires, '3 paires d\'Alice et 2 de Bob');
        self::assertEquals(['voie' => $dievre->getId(), 's' => [70, 60], 'c' => [75, 61]], $paires[4]);
        self::assertCount(0, $this->client->request('GET', '/')->filter('.alerte-photo'), 'plus de proposition');
    }

    public function testUneVoieValideeChangeDePhotoSansPerdreSesCroix(): void
    {
        $claire = $this->creerUtilisateur('claire');
        $arete = $this->voie($claire, 'Arête', [[40, 40], [60, 20]]);
        $this->valider($arete, $this->creerUtilisateur('dan'), '6b');
        $cotation = $arete->getCotation();

        // Claire lance elle-même le changement : c'est sa voie qui sera proposée aux autres
        $this->client->loginUser($claire);
        $form = $this->formulaire($arete);
        $this->deplacer($form, $this->nouvelle, fn (array $p) => ['x' => $p['x'] - 10, 'y' => $p['y'] + 2]);
        $this->client->submit($form);

        self::assertResponseRedirects('/voies/'.$arete->getId());
        $this->em->clear();
        $arete = $this->em->find(Voie::class, $arete->getId());
        self::assertSame($this->nouvelle->getId(), $arete->getPhoto()->getId());
        self::assertSame([[30.0, 42.0], [50.0, 22.0]], array_map(fn (Prise $p) => [$p->getX(), $p->getY()], $arete->getPrises()->toArray()));
        self::assertCount(1, $arete->getCroix(), 'les croix restent');
        self::assertSame($cotation, $arete->getCotation());
        self::assertCount(2, $this->recalage()->getPaires());
    }

    public function testLeCreateurPeutIgnorerLaProposition(): void
    {
        $bob = $this->creerUtilisateur('bob');
        $dievre = $this->voie($bob, 'Dièdre', [[30, 30]]);
        $this->changerDePhoto($this->voie($this->creerUtilisateur('alice'), 'Traversée', [[10, 10]]));
        $url = '/voies/'.$dievre->getId().'/nouvelle-photo/'.$this->recalage()->getId();

        $this->client->loginUser($bob);
        $page = $this->client->request('GET', $url);
        $this->client->request('POST', "$url/ignorer", ['_token' => $this->jeton($page, "$url/ignorer")]);

        self::assertResponseRedirects('/');
        self::assertSame(['La voie « Dièdre » garde sa photo actuelle.'], $this->flashsApresRedirection('success'));
        self::assertCount(0, $this->client->getCrawler()->filter('.alerte-photo'));
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);
    }

    public function testSeulLeCreateurChangeLaPhotoOuIgnoreLaProposition(): void
    {
        $dievre = $this->voie($this->creerUtilisateur('bob'), 'Dièdre', [[30, 30]]);
        $this->changerDePhoto($this->voie($this->creerUtilisateur('alice'), 'Traversée', [[10, 10]]));
        $url = '/voies/'.$dievre->getId().'/nouvelle-photo/'.$this->recalage()->getId();

        $this->client->loginUser($this->creerUtilisateur('eve'));
        foreach ([$url, '/voies/'.$dievre->getId().'/photo'] as $page) {
            $this->client->request('GET', $page);
            self::assertResponseStatusCodeSame(403, $page);
        }
        $this->client->request('POST', "$url/ignorer", ['_token' => 'x']);
        self::assertResponseStatusCodeSame(403);
        self::assertCount(0, $this->recalage()->getVoiesIgnorees());
    }

    public function testLEditeurConnaitLesPrisesDeChaquePhoto(): void
    {
        $bob = $this->creerUtilisateur('bob');
        $dievre = $this->voie($bob, 'Dièdre', [[30, 30]]);
        $o = $dievre->getPrises()->first()->getId();
        $this->changerDePhoto($this->voie($this->creerUtilisateur('alice'), 'Traversée', [[10, 10]]));

        $this->client->loginUser($bob);
        $page = $this->client->request('GET', '/voies/'.$dievre->getId().'/photo');
        $parPhoto = json_decode($page->filter('#prises-par-photo')->text(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([['x' => 30, 'y' => 30, 'r' => 3, 'type' => 'main', 'o' => $o]], $parPhoto[$this->ancienne->getId()]);
        self::assertEqualsWithDelta(35, $parPhoto[$this->nouvelle->getId()][0]['x'], 0.01);
        self::assertSame($o, $parPhoto[$this->nouvelle->getId()][0]['o']);
    }

    /** @return iterable<string, array{string}> */
    public static function langues(): iterable
    {
        foreach (['fr', 'en', 'es', 'it', 'ru'] as $langue) {
            yield $langue => [$langue];
        }
    }

    #[DataProvider('langues')]
    public function testLesNouvellesPagesSontTraduites(string $langue): void
    {
        $bob = $this->creerUtilisateur('bob');
        $dievre = $this->voie($bob, 'Dièdre', [[30, 30]]);
        $validee = $this->voie($bob, 'Arête', [[40, 40]]);
        $this->valider($validee, $this->creerUtilisateur('dan'));
        $this->changerDePhoto($this->voie($this->creerUtilisateur('alice'), 'Traversée', [[10, 10]]));
        $id = $this->recalage()->getId();
        $this->client->getCookieJar()->set(new Cookie(Langues::COOKIE, $langue));
        $this->client->loginUser($bob);

        $urls = ['/', '/voies/'.$dievre->getId()."/nouvelle-photo/$id", '/voies/'.$dievre->getId()."/photo?proposition=$id",
            '/voies/'.$validee->getId().'/photo', '/voies/'.$validee->getId().'/modifier', '/voies/'.$dievre->getId().'/modifier'];
        foreach ($urls as $url) {
            $page = $this->client->request('GET', $url);
            self::assertResponseIsSuccessful("$url ($langue)");
            self::assertDoesNotMatchRegularExpression('/\b(?:accueil|voie|flash)\.[a-z_.]+\b/', $page->filter('body')->text(), "$url ($langue)");
        }
        self::assertCount(2, $this->client->request('GET', '/')->filter('.alerte-photo'));
    }

    // ------------------------------------------------------------------------

    private function photo(User $auteur, string $fichier): Photo
    {
        $photo = (new Photo())->setFichier($fichier)->setLargeur(800)->setHauteur(600)
            ->setSecteur($this->secteur)->setAuteur($auteur);
        $this->em->persist($photo);
        $this->em->flush();

        return $photo;
    }

    /** @param list<array{float|int, float|int}> $positions */
    private function voie(User $auteur, string $nom, array $positions): Voie
    {
        // Relues : une requête du client vide l'EntityManager
        $voie = (new Voie())->setNom($nom)
            ->setAuteur($this->em->find(User::class, $auteur->getId()))
            ->setPhoto($this->em->find(Photo::class, $this->ancienne->getId()))
            ->setCotationFr(CotationFr::tryFrom('6a'));
        foreach ($positions as [$x, $y]) {
            $voie->addPrise((new Prise())->setX($x)->setY($y));
        }
        $this->em->persist($voie);
        $this->em->flush();

        return $voie;
    }

    /** Le créateur passe sa voie sur la nouvelle photo, prises décalées de 5 % vers la droite. */
    private function changerDePhoto(Voie $voie): void
    {
        $this->client->loginUser($voie->getAuteur());
        $form = $this->formulaire($voie);
        $this->deplacer($form, $this->nouvelle, fn (array $p) => ['x' => $p['x'] + 5, 'y' => $p['y']]);
        $this->client->submit($form);
        self::assertResponseRedirects();
    }

    /** Choisit la photo et déplace les prises du formulaire, en gardant leur origine (comme l'éditeur). */
    private function deplacer(Form $form, Photo $photo, callable $deplacement): void
    {
        $form['voie[photo]'] = (string) $photo->getId();
        $prises = json_decode((string) $form['voie[prises]']->getValue(), true, flags: \JSON_THROW_ON_ERROR);
        $form['voie[prises]'] = json_encode(array_map(fn (array $p) => $deplacement($p) + $p, $prises), \JSON_THROW_ON_ERROR);
    }

    /** Formulaire de la page « Changer de photo ». */
    private function formulaire(Voie $voie): Form
    {
        $page = $this->client->request('GET', '/voies/'.$voie->getId().'/photo');
        self::assertResponseIsSuccessful();

        return $page->filter('form[name="voie"]')->form();
    }

    /** Le seul recalage, relu en base (les photos du test sont relues aussi). */
    private function recalage(): Recalage
    {
        $this->em->clear();
        $this->secteur = $this->em->find(Secteur::class, $this->secteur->getId());
        $this->ancienne = $this->em->find(Photo::class, $this->ancienne->getId());
        $this->nouvelle = $this->em->find(Photo::class, $this->nouvelle->getId());
        $recalages = $this->em->getRepository(Recalage::class)->findAll();
        self::assertCount(1, $recalages);

        return $recalages[0];
    }
}
