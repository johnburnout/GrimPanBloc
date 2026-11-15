<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Photo;
use App\Entity\Prise;
use App\Entity\Secteur;
use App\Entity\User;
use App\Entity\Voie;
use App\Enum\CotationCouleur;
use App\Enum\CotationFr;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Listes de voies (accueil et page d'un secteur) : mode icônes ou liste,
 * mêmes filtres, tri au choix (icônes) ou par les en-têtes de colonne (liste).
 */
final class ListeVoiesTest extends FonctionnelTestCase
{
    private Secteur $dalle;
    private Secteur $devers;

    protected function setUp(): void
    {
        parent::setUp();
        $alice = $this->creerUtilisateur('alice');
        $bruno = $this->creerUtilisateur('bruno');
        $this->dalle = $this->secteur('Dalle');
        $this->devers = $this->secteur('Dévers');

        $this->voie('Arête', $this->dalle, $bruno, CotationFr::C6b);
        $this->voie('Bloc', $this->dalle, $alice, CotationFr::C5a);
        $this->voie('Cheminée', $this->devers, $alice, CotationFr::C7a);
        $this->voie('Rouge', $this->devers, $bruno, CotationCouleur::Bleu);
    }

    public function testParDefautIconesTrieesParCotation(): void
    {
        $page = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(['Rouge', 'Bloc', 'Arête', 'Cheminée'], $this->nomsIcones($page), 'couleurs puis française, difficulté croissante');
        self::assertSame('Icônes', $page->filter('.choix-vue a[aria-current]')->text());
        self::assertCount(1, $page->filter('#taille-icones'), 'curseur de taille des icônes');
        self::assertCount(1, $page->filter('#filtres-voies select[name="secteur"]'));
        self::assertSame('4 voies', $page->filter('.nombre-voies')->text());
    }

    public function testValiderAppliqueFiltresEtTriEnModeIcones(): void
    {
        $page = $this->client->request('GET', '/');
        $form = $page->selectButton('Valider')->form([
            'secteur' => (string) $this->dalle->getId(),
            'systeme' => 'fr',
            'tri' => 'nom',
            'ordre' => 'desc',
        ]);
        $page = $this->client->submit($form);

        self::assertSame(['Bloc', 'Arête'], $this->nomsIcones($page));
        self::assertSame('nom', $page->filter('select[name="tri"] option[selected]')->attr('value'));
        self::assertSame('desc', $page->filter('select[name="ordre"] option[selected]')->attr('value'));
    }

    public function testPlageDeDifficulte(): void
    {
        $page = $this->client->request('GET', '/?systeme=fr&min=6a&max=7c');

        self::assertSame(['Arête', 'Cheminée'], $this->nomsIcones($page));
    }

    public function testModeListeAvecColonnesEtTriParEnTete(): void
    {
        $page = $this->client->request('GET', '/');
        $page = $this->client->click($page->selectLink('Liste')->link());

        self::assertSame('Liste', $page->filter('.choix-vue a[aria-current]')->text());
        self::assertSame(['Nom', 'Cotation', 'Secteur', 'Ouvreur'], $this->entetes($page));
        self::assertCount(1, $page->filter('#afficher-miniatures'), 'case à cocher des miniatures');
        self::assertCount(4, $page->filter('#table-voies tbody .col-miniature .photo-voie'));
        self::assertCount(0, $page->filter('select[name="tri"]'), 'en liste, le tri se choisit par les en-têtes');

        // Clic sur « Ouvreur » : tri croissant, puis décroissant au second clic
        $page = $this->client->click($page->filter('#table-voies th a')->reduce(fn (Crawler $a) => str_starts_with(trim($a->text()), 'Ouvreur'))->link());
        self::assertSame('ascending', $page->filter('th[aria-sort]')->attr('aria-sort'));
        self::assertSame(['alice', 'alice', 'bruno', 'bruno'], $this->colonne($page, 4));
        $page = $this->client->click($page->filter('th[aria-sort] a')->link());
        self::assertSame('descending', $page->filter('th[aria-sort]')->attr('aria-sort'));
        self::assertSame(['bruno', 'bruno', 'alice', 'alice'], $this->colonne($page, 4));

        // Valider les filtres garde le mode liste et le tri choisi
        $page = $this->client->submit($page->selectButton('Valider')->form(['secteur' => (string) $this->devers->getId()]));
        self::assertSame('Liste', $page->filter('.choix-vue a[aria-current]')->text());
        self::assertSame(['Rouge', 'Cheminée'], $this->colonne($page, 1));
        self::assertSame('descending', $page->filter('th[aria-sort]')->attr('aria-sort'));
    }

    public function testPageDUnSecteurMemesFiltresSansSecteur(): void
    {
        $url = '/secteurs/'.$this->dalle->getId();
        $page = $this->client->request('GET', $url);

        self::assertSame(['Bloc', 'Arête'], $this->nomsIcones($page));
        self::assertCount(0, $page->filter('#filtres-voies select[name="secteur"]'));
        self::assertCount(1, $page->filter('#filtres-voies select[name="systeme"]'));
        self::assertSame(['cotation', 'nom', 'ouvreur'], $page->filter('select[name="tri"] option')->each(fn (Crawler $o) => $o->attr('value')));

        // Le paramètre secteur d'un autre lien est sans effet ici
        $page = $this->client->request('GET', $url.'?vue=liste&secteur='.$this->devers->getId());
        self::assertSame(['Nom', 'Cotation', 'Ouvreur'], $this->entetes($page));
        self::assertSame(['Bloc', 'Arête'], $this->colonne($page, 1));
        self::assertSame($url, parse_url((string) $page->selectLink('Icônes')->attr('href'), \PHP_URL_PATH), 'on reste sur la page du secteur');
    }

    public function testAucuneVoieNeCorrespond(): void
    {
        $page = $this->client->request('GET', '/?systeme=fr&min=9a');

        self::assertSame('Aucune voie ne correspond à ces filtres.', $page->filter('.vide')->text());
    }

    public function testTriInconnuRefuse(): void
    {
        $this->client->request('GET', '/?tri=pirate');

        self::assertResponseStatusCodeSame(404);
    }

    /** @return list<string> */
    private function nomsIcones(Crawler $page): array
    {
        return $page->filter('#grille-voies .carte-infos strong')->each(fn (Crawler $n) => $n->text());
    }

    /** @return list<string> */
    private function entetes(Crawler $page): array
    {
        return $page->filter('#table-voies thead th:not(.col-miniature)')->each(fn (Crawler $th) => trim(str_replace(['▲', '▼'], '', $th->text())));
    }

    /**
     * Textes d'une colonne du tableau (1 : nom ; la miniature n'est pas comptée).
     *
     * @return list<string>
     */
    private function colonne(Crawler $page, int $n): array
    {
        return $page->filter('#table-voies tbody tr')->each(fn (Crawler $tr) => trim($tr->filter('td:not(.col-miniature)')->eq($n - 1)->text()));
    }

    private function secteur(string $nom): Secteur
    {
        $secteur = (new Secteur())->setNom($nom);
        $this->em->persist($secteur);

        return $secteur;
    }

    private function voie(string $nom, Secteur $secteur, User $auteur, CotationFr|CotationCouleur $cotation): void
    {
        $photo = (new Photo())->setFichier(strtolower($nom).'.jpg')->setLargeur(800)->setHauteur(600)->setSecteur($secteur)->setAuteur($auteur);
        $voie = (new Voie())->setNom($nom)->setAuteur($auteur)->setPhoto($photo);
        $cotation instanceof CotationFr ? $voie->setCotationFr($cotation) : $voie->setCotationCouleur($cotation);
        $voie->addPrise((new Prise())->setX(10)->setY(20));
        $this->em->persist($photo);
        $this->em->persist($voie);
        $this->em->flush();
    }
}
