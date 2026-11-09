<?php

namespace App\Tests\Fonctionnel;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Formulaire de connexion et limitation des essais (login_throttling, security.yaml).
 */
final class ConnexionTest extends FonctionnelTestCase
{
    private const MOT_DE_PASSE = 'bon-mot-de-passe';

    protected function setUp(): void
    {
        parent::setUp();
        // Les compteurs d'essais vivent dans le cache fichier : on repart de zéro,
        // sinon les échecs d'un lancement précédent fausseraient celui-ci.
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testConnexionAvecLeBonMotDePasse(): void
    {
        $this->creerGrimpeurAvecMotDePasse('lea');

        $this->seConnecter('lea@exemple.test', self::MOT_DE_PASSE);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Déconnexion');
    }

    public function testMauvaisMotDePasseAfficheUneErreur(): void
    {
        $this->creerGrimpeurAvecMotDePasse('lea');

        $this->seConnecter('lea@exemple.test', 'faux');

        self::assertResponseRedirects('/connexion');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'Email ou mot de passe incorrect.');
    }

    public function testApresCinqEchecsLeBonMotDePasseEstRefuse(): void
    {
        $this->creerGrimpeurAvecMotDePasse('lea');

        for ($i = 0; $i < 5; ++$i) {
            $this->seConnecter('lea@exemple.test', 'faux-'.$i);
        }
        // Sixième essai, cette fois avec le bon mot de passe : bloqué quand même.
        $this->seConnecter('lea@exemple.test', self::MOT_DE_PASSE);

        self::assertResponseRedirects('/connexion');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'veuillez réessayer dans');
        self::assertSelectorTextNotContains('body', 'Déconnexion');
    }

    public function testLeBlocageNeVisePasLesAutresComptes(): void
    {
        $this->creerGrimpeurAvecMotDePasse('lea');
        $this->creerGrimpeurAvecMotDePasse('tom');

        for ($i = 0; $i < 5; ++$i) {
            $this->seConnecter('lea@exemple.test', 'faux-'.$i);
        }
        $this->seConnecter('tom@exemple.test', self::MOT_DE_PASSE);

        self::assertResponseRedirects('/');
    }

    public function testLeBoutonDeDeconnexionDeconnecte(): void
    {
        $this->client->loginUser($this->creerUtilisateur('lea'));
        $page = $this->client->request('GET', '/');

        $this->client->submit($page->selectButton('Déconnexion')->form());

        self::assertResponseRedirects('/');
        $this->client->request('GET', '/profil');
        self::assertResponseRedirects('/connexion');
    }

    public function testUnLienGetNeDeconnectePlus(): void
    {
        $this->client->loginUser($this->creerUtilisateur('lea'));

        $this->client->request('GET', '/deconnexion');

        self::assertResponseStatusCodeSame(405);
        $this->client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
    }

    public function testUnPostSansJetonValideNeDeconnectePas(): void
    {
        $this->client->loginUser($this->creerUtilisateur('lea'));

        $this->client->request('POST', '/deconnexion', ['_csrf_token' => 'faux']);

        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
    }

    private function creerGrimpeurAvecMotDePasse(string $pseudo): User
    {
        $user = $this->creerUtilisateur($pseudo);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, self::MOT_DE_PASSE));
        $this->em->flush();

        return $user;
    }

    private function seConnecter(string $email, string $motDePasse): void
    {
        $page = $this->client->request('GET', '/connexion');
        $this->client->submit($page->selectButton('Se connecter')->form([
            '_username' => $email,
            '_password' => $motDePasse,
        ]));
    }
}
