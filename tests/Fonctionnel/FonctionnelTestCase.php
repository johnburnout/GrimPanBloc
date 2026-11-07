<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Croix;
use App\Entity\Photo;
use App\Entity\Prise;
use App\Entity\Secteur;
use App\Entity\User;
use App\Entity\Voie;
use App\Enum\CotationFr;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Tests HTTP sur la base grimpepan_test (voir doctrine.yaml, when@test).
 * Chaque test tourne dans une transaction annulée à la fin : la base reste vide.
 */
abstract class FonctionnelTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Même noyau (donc même connexion et même transaction) pour toutes les requêtes du test
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connexion = $this->em->getConnection();
        while ($connexion->isTransactionActive()) {
            $connexion->rollBack();
        }
        parent::tearDown();
    }

    protected function creerUtilisateur(string $pseudo, bool $admin = false): User
    {
        $user = (new User())
            ->setEmail($pseudo.'@exemple.test')
            ->setPseudo($pseudo)
            ->setPassword('!')
            ->setAdmin($admin);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function creerVoie(User $auteur, string $nom = 'Dalle grise'): Voie
    {
        $secteur = (new Secteur())->setNom('Secteur test');
        $photo = (new Photo())->setFichier('absente.jpg')->setLargeur(800)->setHauteur(600)
            ->setSecteur($secteur)->setAuteur($auteur);
        $voie = (new Voie())->setNom($nom)->setAuteur($auteur)->setPhoto($photo)->setCotationFr(CotationFr::tryFrom('6a'));
        $voie->addPrise((new Prise())->setX(10)->setY(20));
        $this->em->persist($secteur);
        $this->em->persist($photo);
        $this->em->persist($voie);
        $this->em->flush();

        return $voie;
    }

    /** Un autre grimpeur que l'auteur réussit la voie : elle devient validée. */
    protected function valider(Voie $voie, User $grimpeur, string $cotation = '6a'): Croix
    {
        $croix = (new Croix($voie, $grimpeur))->setCotation($cotation);
        $voie->ajouterCroix($croix);
        $this->em->persist($croix);
        $this->em->flush();

        return $croix;
    }

    /** Jeton CSRF du premier formulaire qui poste vers $action sur la page affichée. */
    protected function jeton(Crawler $page, string $action): string
    {
        $champ = $page->filter(\sprintf('form[action="%s"] input[name="_token"]', $action));
        self::assertGreaterThan(0, $champ->count(), "Aucun formulaire vers $action sur la page.");

        return (string) $champ->first()->attr('value');
    }

    protected function voieExiste(int $id): bool
    {
        $this->em->clear();

        return null !== $this->em->find(Voie::class, $id);
    }

    /**
     * Suit la redirection et renvoie les messages flash du type demandé.
     *
     * @return list<string>
     */
    protected function flashsApresRedirection(string $type): array
    {
        $this->client->followRedirect();

        return $this->client->getCrawler()->filter(".flash-$type")->each(fn (Crawler $n) => trim($n->text()));
    }
}
