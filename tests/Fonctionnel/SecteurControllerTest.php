<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Photo;
use App\Entity\Prise;
use App\Entity\Secteur;
use App\Entity\Voie;
use Symfony\Component\DomCrawler\Crawler;

/** Liste des secteurs. */
final class SecteurControllerTest extends FonctionnelTestCase
{
    /**
     * Le chiffre affiché est le nombre de voies : une photo restée dans le secteur
     * après la suppression de sa voie ne compte pas.
     */
    public function testLaListeCompteLesVoiesPasLesPhotos(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $voie = $this->creerVoie($auteur, 'Première');
        $secteur = $voie->getPhoto()->getSecteur()->setNom('A — deux voies');
        $this->creerVoieSurPhoto($voie->getPhoto(), 'Deuxième');
        $this->em->persist((new Photo())->setFichier('inutilisee.jpg')->setLargeur(800)->setHauteur(600)->setSecteur($secteur)->setAuteur($auteur));

        $sansVoie = (new Secteur())->setNom('B — photos sans voie');
        $this->em->persist($sansVoie);
        foreach (['p1.jpg', 'p2.jpg', 'p3.jpg'] as $fichier) {
            $this->em->persist((new Photo())->setFichier($fichier)->setLargeur(800)->setHauteur(600)->setSecteur($sansVoie)->setAuteur($auteur));
        }
        $this->em->persist((new Secteur())->setNom('C — vide'));
        $this->em->flush();

        $page = $this->client->request('GET', '/secteurs');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['A — deux voies 2 voies', 'B — photos sans voie 0 voie', 'C — vide 0 voie'],
            $page->filter('.liste-secteurs li')->each(fn (Crawler $li) => preg_replace('/\s+/', ' ', trim($li->text()))),
        );
    }

    private function creerVoieSurPhoto(Photo $photo, string $nom): void
    {
        $voie = (new Voie())->setNom($nom)->setAuteur($photo->getAuteur())->setPhoto($photo);
        $voie->addPrise((new Prise())->setX(10)->setY(20));
        $this->em->persist($voie);
        $this->em->flush();
    }
}
