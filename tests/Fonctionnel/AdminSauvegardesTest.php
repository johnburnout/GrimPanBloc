<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Photo;
use App\Entity\Prise;
use App\Entity\Secteur;
use App\Entity\User;
use App\Entity\Voie;
use App\Enum\CotationFr;
use App\Enum\TypeSauvegarde;
use App\Sauvegarde\ArchiveSauvegarde;
use App\Sauvegarde\DepotSauvegardes;
use App\Sauvegarde\GestionSauvegardes;
use App\Sauvegarde\ReglageSauvegardes;
use App\Sauvegarde\Restauration;
use App\Sauvegarde\Sauvegarde;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Lock\LockFactory;

/**
 * Administration → Sauvegardes (création, téléchargement, import, restauration
 * complète ou d'un secteur, conservation) et nettoyage d'un secteur.
 */
final class AdminSauvegardesTest extends FonctionnelTestCase
{
    private User $admin;
    private string $photos;
    private DepotSauvegardes $depot;
    private GestionSauvegardes $gestion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->creerUtilisateur('admin', admin: true);
        $this->photos = (string) static::getContainer()->getParameter('photos_dir');
        $this->depot = static::getContainer()->get(DepotSauvegardes::class);
        $this->gestion = static::getContainer()->get(GestionSauvegardes::class);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->photos);
        parent::tearDown();
    }

    public function testCreerPuisTelechargerUneSauvegarde(): void
    {
        $voie = $this->voieAvecPhoto('Dévers', 'Le toit', 'toit.jpg');
        $this->valider($voie, $this->creerUtilisateur('grimpeur'), '6b');

        $this->client->loginUser($this->admin);
        $page = $this->client->request('GET', '/admin/sauvegardes');
        self::assertSelectorTextContains('.vide', 'Aucune sauvegarde');
        $this->client->submit($page->selectButton('Sauvegarder maintenant')->form(['motif' => 'avant juin']));

        self::assertResponseRedirects('/admin/sauvegardes');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.tableau-sauvegardes', 'Manuelle');
        self::assertSelectorTextContains('.tableau-sauvegardes', 'avant juin');
        self::assertSelectorTextContains('.tableau-sauvegardes', '1 secteur, 1 voie, 1 photo');

        $this->client->click($this->client->getCrawler()->selectLink('Télécharger')->link());
        $reponse = $this->client->getResponse();
        self::assertResponseIsSuccessful();
        self::assertInstanceOf(BinaryFileResponse::class, $reponse);
        self::assertStringContainsString('attachment', (string) $reponse->headers->get('Content-Disposition'));

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($reponse->getFile()->getPathname()));
        self::assertNotFalse($zip->locateName('photos/toit.jpg'));
        $donnees = json_decode((string) $zip->getFromName('donnees.json'), true);
        $zip->close();
        self::assertSame('Le toit', $donnees['voies'][0]['nom']);
        self::assertSame($voie->getId(), $donnees['voies'][0]['id']);
        self::assertSame('admin', $donnees['voies'][0]['auteur']);
        self::assertCount(1, $donnees['voies'][0]['prises']);
        self::assertSame([['grimpeur' => 'grimpeur', 'cotation' => '6b', 'creeLe' => $donnees['voies'][0]['croix'][0]['creeLe']]], $donnees['voies'][0]['croix']);
    }

    /** Tout remis comme à la sauvegarde : mêmes identifiants, fichiers, croix ; secteur créé depuis supprimé. */
    public function testRestaurationComplete(): void
    {
        $grimpeur = $this->creerUtilisateur('grimpeur');
        $voie = $this->voieAvecPhoto('Dévers', 'Le toit', 'toit.jpg');
        $this->valider($voie, $grimpeur, '6b');
        $idVoie = $voie->getId();
        $idSecteur = $voie->getPhoto()->getSecteur()->getId();
        $sauvegarde = $this->gestion->creerManuelle();

        // Après la sauvegarde : la voie et sa photo disparaissent, un secteur apparaît
        $this->em->remove($voie);
        $this->em->remove($voie->getPhoto());
        $this->em->persist((new Secteur())->setNom('Nouveau'));
        $this->em->flush();
        unlink($this->photos.'/toit.jpg');

        $this->client->loginUser($this->admin);
        $this->restaurer($sauvegarde, 'tout', 'garder');

        self::assertResponseRedirects('/secteurs');
        self::assertStringStartsWith('Sauvegarde restaurée : 1 voie(s), 1 photo(s) et 1 croix.', $this->flashsApresRedirection('success')[0]);
        $this->em->clear();
        $restauree = $this->em->find(Voie::class, $idVoie);
        self::assertNotNull($restauree, 'Même identifiant : liens et QR codes restent valables.');
        self::assertSame('Le toit', $restauree->getNom());
        self::assertSame($idSecteur, $restauree->getPhoto()->getSecteur()->getId());
        self::assertCount(1, $restauree->getPrises());
        self::assertSame($grimpeur->getId(), $restauree->getCroix()->first()->getGrimpeur()->getId());
        self::assertTrue($restauree->estValidee());
        self::assertFileExists($this->photos.'/toit.jpg');
        self::assertNull($this->em->getRepository(Secteur::class)->findOneBy(['nom' => 'Nouveau']));
        // Sauvegarde gardée (choix de l'admin) + sauvegarde automatique de l'état d'avant
        $types = array_map(fn (Sauvegarde $s) => $s->type, $this->depot->lister());
        self::assertEqualsCanonicalizing([TypeSauvegarde::Manuelle, TypeSauvegarde::Automatique], $types);
    }

    public function testRestaurationDUnSeulSecteur(): void
    {
        $a = $this->voieAvecPhoto('Dévers', 'Voie A', 'a.jpg');
        $b = $this->voieAvecPhoto('Dalle', 'Voie B', 'b.jpg');
        $idA = $a->getId();
        $secteurA = $a->getPhoto()->getSecteur()->getId();
        $sauvegarde = $this->gestion->creerManuelle();
        // Après la sauvegarde : A renommée, B supprimée
        $idB = $b->getId();
        $a->setNom('A modifiée');
        $this->em->remove($b);
        $this->em->flush();

        $this->client->loginUser($this->admin);
        $page = $this->client->request('GET', '/admin/sauvegardes/'.$sauvegarde->nom.'/restaurer');
        self::assertStringContainsString('remplace tout le contenu actuel du secteur « Dévers »', $page->text());
        $this->restaurer($sauvegarde, (string) $secteurA, 'garder');

        self::assertResponseRedirects('/secteurs/'.$secteurA);
        $this->em->clear();
        self::assertSame('Voie A', $this->em->find(Voie::class, $idA)?->getNom());
        self::assertNull($this->em->find(Voie::class, $idB), 'Le secteur Dalle n\'est pas touché.');
    }

    /** Sauvegarde manuelle : l'admin doit dire s'il la garde ; ici, il la supprime. */
    public function testRestaurerUneSauvegardeManuelleDemandeSiOnLaSupprime(): void
    {
        $this->voieAvecPhoto('Dévers', 'Le toit', 'toit.jpg');
        $sauvegarde = $this->gestion->creerManuelle();
        $this->client->loginUser($this->admin);

        $page = $this->client->request('GET', '/admin/sauvegardes/'.$sauvegarde->nom.'/restaurer');
        self::assertCount(2, $page->filter('input[name="apres"]'));
        $this->client->request('POST', '/admin/sauvegardes/'.$sauvegarde->nom.'/restaurer', [
            '_token' => $this->jetonRestauration($sauvegarde), 'portee' => 'tout', 'confirmation' => 'RESTAURER',
        ]);
        self::assertSame(['Indiquez s\'il faut garder cette sauvegarde après la restauration.'], $this->flashsApresRedirection('error'));
        self::assertCount(1, $this->depot->lister(), 'Rien n\'a été fait.');

        $this->restaurer($sauvegarde, 'tout', 'supprimer');
        self::assertContains('La sauvegarde restaurée a été supprimée.', $this->flashsApresRedirection('info'));
        self::assertNull($this->depot->trouver($sauvegarde->nom));
        self::assertSame([TypeSauvegarde::Automatique], array_map(fn (Sauvegarde $s) => $s->type, $this->depot->lister()));
    }

    public function testUneSauvegardeAutomatiqueNePosePasLaQuestion(): void
    {
        $sauvegarde = $this->gestion->creerAutomatique('test');
        $this->client->loginUser($this->admin);

        $page = $this->client->request('GET', '/admin/sauvegardes/'.$sauvegarde->nom.'/restaurer');

        self::assertCount(0, $page->filter('input[name="apres"]'));
    }

    public function testRestaurationSansLeMotDeConfirmation(): void
    {
        $voie = $this->voieAvecPhoto('Dévers', 'Le toit', 'toit.jpg');
        $sauvegarde = $this->gestion->creerAutomatique('test');
        $voie->setNom('Modifiée');
        $this->em->flush();
        $this->client->loginUser($this->admin);

        $this->client->request('POST', '/admin/sauvegardes/'.$sauvegarde->nom.'/restaurer', [
            '_token' => $this->jetonRestauration($sauvegarde), 'portee' => 'tout', 'confirmation' => 'oui',
        ]);

        self::assertSame(['Restauration annulée : tapez RESTAURER pour confirmer.'], $this->flashsApresRedirection('error'));
        $this->em->clear();
        self::assertSame('Modifiée', $this->em->find(Voie::class, $voie->getId())?->getNom());
    }

    /** Auteur inconnu sur ce site (autre installation) : voie attribuée à l'admin, croix ignorée. */
    public function testAuteurEtGrimpeurInconnus(): void
    {
        $chemin = $this->archive([
            'secteurs' => [['id' => 9001, 'nom' => 'Importé', 'description' => null]],
            'photos' => [$this->photoArchive(9001, 9001, 'importee.jpg', 'inconnu')],
            'voies' => [$this->voieArchive(9001, 9001, 'inconnu', [['grimpeur' => 'fantome', 'cotation' => '6a', 'creeLe' => '2026-01-01 10:00:00']])],
        ], ['photos/importee.jpg' => $this->jpeg()]);

        $this->client->loginUser($this->admin);
        $this->client->request('POST', '/admin/sauvegardes/importer', ['import' => ['_token' => $this->jetonImport()]], ['import' => ['archive' => new UploadedFile($chemin, 'sauvegarde.zip', 'application/zip', null, true)]]);
        $sauvegarde = $this->depot->lister()[0];
        self::assertSame(TypeSauvegarde::Importee, $sauvegarde->type);
        self::assertResponseRedirects('/admin/sauvegardes/'.$sauvegarde->nom.'/restaurer');

        $this->restaurer($sauvegarde, '9001');
        self::assertStringContainsString('1 croix ignorée(s) : grimpeur absent de ce site.', $this->flashsApresRedirection('success')[0]);
        $this->em->clear();
        $voie = $this->em->find(Voie::class, 9001);
        self::assertSame('admin', $voie?->getAuteur()?->getPseudo());
        self::assertCount(0, $voie->getCroix());
        self::assertFileExists($this->photos.'/importee.jpg');
    }

    public function testImportDUnFichierQuiNEstPasUneSauvegarde(): void
    {
        $chemin = tempnam(sys_get_temp_dir(), 'zip').'.zip';
        $zip = new \ZipArchive();
        $zip->open($chemin, \ZipArchive::CREATE);
        $zip->addFromString('autre.txt', 'rien');
        $zip->close();

        $this->client->loginUser($this->admin);
        $this->client->request('POST', '/admin/sauvegardes/importer', ['import' => ['_token' => $this->jetonImport()]], ['import' => ['archive' => new UploadedFile($chemin, 'autre.zip', 'application/zip', null, true)]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="import"]', 'Ce fichier n\'est pas une sauvegarde GrimPanBloc.');
        self::assertSame([], $this->depot->lister());
    }

    /** Une « photo » qui n'est pas une image n'entre jamais dans uploads/photos/, et rien n'est modifié. */
    public function testArchiveAvecUnFauxFichierPhotoRefusee(): void
    {
        $voie = $this->voieAvecPhoto('Dévers', 'Le toit', 'toit.jpg');
        $chemin = $this->archive([
            'secteurs' => [['id' => 9001, 'nom' => 'Piège', 'description' => null]],
            'photos' => [$this->photoArchive(9001, 9001, 'piege.jpg', 'admin')],
            'voies' => [],
        ], ['photos/piege.jpg' => '<?php echo "pirate";']);
        $sauvegarde = $this->deposer($chemin);

        $this->client->loginUser($this->admin);
        $this->restaurer($sauvegarde, 'tout');

        self::assertStringContainsString('photos/piege.jpg n\'est pas une image', $this->flashsApresRedirection('error')[0]);
        self::assertFileDoesNotExist($this->photos.'/piege.jpg');
        $this->em->clear();
        self::assertNotNull($this->em->find(Voie::class, $voie->getId()));
    }

    public function testArchiveAuxDonneesIncoherentesRefusee(): void
    {
        $chemin = $this->archive([
            'secteurs' => [['id' => 1, 'nom' => 'S', 'description' => null]],
            'photos' => [$this->photoArchive(1, 99, 'p.jpg', 'admin')], // secteur 99 absent
            'voies' => [],
        ], []);

        $this->expectException(\InvalidArgumentException::class);
        static::getContainer()->get(ArchiveSauvegarde::class)->lireDonnees($chemin);
    }

    public function testNettoyerUnSecteur(): void
    {
        $voie = $this->voieAvecPhoto('Dévers', 'Le toit', 'toit.jpg');
        $this->valider($voie, $this->creerUtilisateur('grimpeur'));
        $secteur = $voie->getPhoto()->getSecteur();
        $libre = $this->photo($secteur, 'libre.jpg'); // photo inutilisée : supprimée aussi
        $autre = $this->voieAvecPhoto('Dalle', 'Voisine', 'voisine.jpg');
        $idVoie = $voie->getId();
        $url = '/admin/secteurs/'.$secteur->getId().'/nettoyer';

        $this->client->loginUser($this->admin);
        $page = $this->client->request('GET', '/secteurs/'.$secteur->getId());
        $page = $this->client->click($page->selectLink('Nettoyer le secteur…')->link());
        self::assertStringContainsString('1 voie(s)', $page->filter('.zone-danger')->text());
        self::assertStringContainsString('2 photo(s)', $page->filter('.zone-danger')->text());
        self::assertStringContainsString('1 croix', $page->filter('.zone-danger')->text());

        // Garde-fou : sans le mot, rien
        $this->client->submit($page->selectButton('Nettoyer le secteur')->form(['confirmation' => 'oui']));
        self::assertResponseRedirects($url);
        self::assertTrue($this->voieExiste($idVoie));

        $page = $this->client->request('GET', $url);
        $this->client->submit($page->selectButton('Nettoyer le secteur')->form(['confirmation' => 'SUPPRIMER']));

        self::assertResponseRedirects('/secteurs/'.$secteur->getId());
        self::assertSame(['Secteur « Dévers » nettoyé : 1 voie(s) et 2 photo(s) supprimée(s).'], $this->flashsApresRedirection('success'));
        self::assertFalse($this->voieExiste($idVoie));
        self::assertNull($this->em->find(Photo::class, $libre->getId()));
        self::assertNotNull($this->em->find(Secteur::class, $secteur->getId()), 'Le secteur reste.');
        self::assertTrue($this->voieExiste($autre->getId()));
        self::assertFileDoesNotExist($this->photos.'/toit.jpg');
        self::assertFileDoesNotExist($this->photos.'/libre.jpg');
        self::assertFileExists($this->photos.'/voisine.jpg');

        // La sauvegarde automatique faite avant permet de tout récupérer
        $avant = $this->depot->lister();
        self::assertCount(1, $avant);
        self::assertSame(TypeSauvegarde::Automatique, $avant[0]->type);
        $this->restaurer($avant[0], (string) $secteur->getId());
        self::assertTrue($this->voieExiste($idVoie));
        self::assertFileExists($this->photos.'/toit.jpg');
        self::assertFileExists($this->photos.'/libre.jpg');
    }

    public function testConservationDesSauvegardesAutomatiques(): void
    {
        $this->client->loginUser($this->admin);
        $page = $this->client->request('GET', '/admin/sauvegardes');
        self::assertSame((string) ReglageSauvegardes::DEFAUT, $page->filter('#conservation_automatiques')->attr('value'));

        $this->client->submit($page->selectButton('Enregistrer')->form(['conservation[automatiques]' => '11']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('form[name="conservation"] ul li');

        $manuelle = $this->gestion->creerManuelle();
        for ($i = 0; $i < 5; ++$i) {
            $this->gestion->creerAutomatique("auto $i");
        }
        self::assertCount(6, $this->depot->lister(), '5 automatiques (réglage par défaut) + 1 manuelle.');

        $page = $this->client->request('GET', '/admin/sauvegardes');
        $this->client->submit($page->selectButton('Enregistrer')->form(['conservation[automatiques]' => '3']));
        self::assertResponseRedirects('/admin/sauvegardes');
        self::assertStringContainsString('2 sauvegarde(s) plus ancienne(s) supprimée(s)', $this->flashsApresRedirection('success')[0]);
        $restantes = $this->depot->lister();
        self::assertCount(4, $restantes);
        self::assertContains($manuelle->nom, array_map(fn (Sauvegarde $s) => $s->nom, $restantes), 'Les manuelles ne sont jamais purgées.');

        $this->gestion->creerAutomatique('encore');
        self::assertCount(3, array_filter($this->depot->lister(), fn (Sauvegarde $s) => TypeSauvegarde::Automatique === $s->type));
    }

    public function testSupprimerUneSauvegarde(): void
    {
        $sauvegarde = $this->gestion->creerManuelle();
        $this->client->loginUser($this->admin);
        $page = $this->client->request('GET', '/admin/sauvegardes');

        $this->client->submit($page->filter('.tableau-sauvegardes form')->form());

        self::assertResponseRedirects('/admin/sauvegardes');
        self::assertNull($this->depot->trouver($sauvegarde->nom));
    }

    /** Sans cron : sauvegarde si la dernière date de plus de 7 jours, une seule fois. */
    public function testSauvegardeReguliere(): void
    {
        $c = static::getContainer();
        $gestion = new GestionSauvegardes($this->depot, $c->get(ArchiveSauvegarde::class), $c->get(Restauration::class), $c->get(ReglageSauvegardes::class), $c->get(LockFactory::class), new NullLogger(), 7);

        self::assertNotNull($gestion->sauvegardeReguliereSiNecessaire());
        self::assertNull($gestion->sauvegardeReguliereSiNecessaire(), 'La dernière date d\'aujourd\'hui.');
        self::assertCount(1, $this->depot->lister());
    }

    public function testReserveAuxAdministrateurs(): void
    {
        $sauvegarde = $this->gestion->creerManuelle();
        $voie = $this->voieAvecPhoto('Dévers', 'Le toit', 'toit.jpg');
        $this->client->loginUser($this->creerUtilisateur('grimpeur'));

        foreach (['/admin/sauvegardes', '/admin/sauvegardes/'.$sauvegarde->nom.'/telecharger', '/admin/sauvegardes/'.$sauvegarde->nom.'/restaurer', '/admin/secteurs/'.$voie->getPhoto()->getSecteur()->getId().'/nettoyer'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }
        $page = $this->client->request('GET', '/secteurs/'.$voie->getPhoto()->getSecteur()->getId());
        self::assertCount(0, $page->selectLink('Nettoyer le secteur…'));
    }

    public function testNomDeSauvegardeInvalide(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/sauvegardes/..%2F..%2F.env/telecharger');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/sauvegardes/sauvegarde-20260101-000000-manuelle.zip/telecharger');
        self::assertResponseStatusCodeSame(404);
    }

    // ---------------------------------------------------------------------------

    private function restaurer(Sauvegarde $s, string $portee, ?string $apres = null): void
    {
        $this->client->request('POST', '/admin/sauvegardes/'.$s->nom.'/restaurer', array_filter([
            '_token' => $this->jetonRestauration($s), 'portee' => $portee, 'confirmation' => 'RESTAURER', 'apres' => $apres,
        ]));
    }

    private function jetonRestauration(Sauvegarde $s): string
    {
        $page = $this->client->request('GET', '/admin/sauvegardes/'.$s->nom.'/restaurer');

        return (string) $page->filter('input[name="_token"]')->attr('value');
    }

    private function jetonImport(): string
    {
        $page = $this->client->request('GET', '/admin/sauvegardes');

        return (string) $page->filter('#import__token')->attr('value');
    }

    private function voieAvecPhoto(string $secteurNom, string $nom, string $fichier): Voie
    {
        $secteur = (new Secteur())->setNom($secteurNom);
        $this->em->persist($secteur);
        $photo = $this->photo($secteur, $fichier);
        $voie = (new Voie())->setNom($nom)->setAuteur($this->admin)->setPhoto($photo)->setCotationFr(CotationFr::tryFrom('6a'));
        $voie->addPrise((new Prise())->setX(10)->setY(20));
        $this->em->persist($voie);
        $this->em->flush();

        return $voie;
    }

    private function photo(Secteur $secteur, string $fichier): Photo
    {
        (new Filesystem())->dumpFile($this->photos.'/'.$fichier, $this->jpeg());
        $photo = (new Photo())->setFichier($fichier)->setLargeur(16)->setHauteur(12)->setSecteur($secteur)->setAuteur($this->admin);
        $this->em->persist($photo);
        $this->em->flush();

        return $photo;
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(16, 12);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    /** @return array<string, mixed> */
    private function photoArchive(int $id, int $secteur, string $fichier, string $auteur): array
    {
        return ['id' => $id, 'secteur' => $secteur, 'fichier' => $fichier, 'largeur' => 16, 'hauteur' => 12, 'auteur' => $auteur, 'creeLe' => '2026-01-01 10:00:00'];
    }

    /**
     * @param list<array<string, mixed>> $croix
     *
     * @return array<string, mixed>
     */
    private function voieArchive(int $id, int $photo, string $auteur, array $croix = []): array
    {
        return [
            'id' => $id, 'photo' => $photo, 'nom' => 'Voie importée', 'description' => null, 'auteur' => $auteur,
            'systeme' => 'fr', 'cotation' => '6a', 'cotationAuteur' => '6a', 'rang' => 10, 'active' => true, 'creeLe' => '2026-01-01 10:00:00',
            'prises' => [['x' => 10.0, 'y' => 20.0, 'rayon' => 3.0, 'type' => 'main']], 'croix' => $croix,
        ];
    }

    /**
     * Archive fabriquée à la main (comme pourrait l'être une archive envoyée).
     *
     * @param array<string, mixed> $donnees
     * @param array<string, string> $fichiers
     */
    private function archive(array $donnees, array $fichiers): string
    {
        $chemin = sys_get_temp_dir().'/grimpanbloc-archive-'.bin2hex(random_bytes(4)).'.zip';
        $secteurs = array_map(fn (array $s) => ['id' => $s['id'], 'nom' => $s['nom'], 'voies' => 0, 'photos' => 0], $donnees['secteurs']);
        $zip = new \ZipArchive();
        $zip->open($chemin, \ZipArchive::CREATE);
        $zip->addFromString('resume.json', (string) json_encode(['format' => 1, 'version' => '1.4.0', 'type' => 'manuelle', 'motif' => '', 'creeLe' => date(\DATE_ATOM), 'secteurs' => $secteurs]));
        $zip->addFromString('donnees.json', (string) json_encode(['format' => 1] + $donnees, \JSON_PRESERVE_ZERO_FRACTION));
        foreach ($fichiers as $nom => $contenu) {
            $zip->addFromString($nom, $contenu);
        }
        $zip->close();

        return $chemin;
    }

    private function deposer(string $chemin): Sauvegarde
    {
        $cible = $this->depot->nouveauChemin(TypeSauvegarde::Importee);
        rename($chemin, $cible);

        return $this->depot->trouver(basename($cible)) ?? throw new \LogicException();
    }
}
