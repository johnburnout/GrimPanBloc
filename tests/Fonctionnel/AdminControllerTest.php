<?php

namespace App\Tests\Fonctionnel;

use App\Entity\User;

final class AdminControllerTest extends FonctionnelTestCase
{
    // --- Utilisateurs -------------------------------------------------------

    public function testSuppressionDUnCompteExigeLeMotDeConfirmation(): void
    {
        $admin = $this->creerUtilisateur('admin', admin: true);
        $cible = $this->creerUtilisateur('cible');
        $id = $cible->getId();

        $this->client->loginUser($admin);
        $page = $this->client->request('GET', "/admin/utilisateurs/$id/supprimer");
        self::assertSelectorTextContains('.zone-danger label', 'SUPPRIMER');
        $jeton = (string) $page->filter('.zone-danger input[name="_token"]')->attr('value');

        $this->client->request('POST', "/admin/utilisateurs/$id/supprimer", ['_token' => $jeton, 'confirmation' => 'non']);
        self::assertResponseRedirects("/admin/utilisateurs/$id/supprimer");
        self::assertContains('Suppression annulée : tapez SUPPRIMER pour confirmer.', $this->flashsApresRedirection('error'));
        self::assertFalse($this->recharger($id)->estSupprime());

        $this->client->request('POST', "/admin/utilisateurs/$id/supprimer", ['_token' => $jeton, 'confirmation' => 'SUPPRIMER']);
        self::assertResponseRedirects('/admin/utilisateurs');
        self::assertTrue($this->recharger($id)->estSupprime());
    }

    public function testSuppressionDUnCompteRefuseeAvecUnJetonInvalide(): void
    {
        $cible = $this->creerUtilisateur('cible');

        $this->client->loginUser($this->creerUtilisateur('admin', admin: true));
        $this->client->request('POST', '/admin/utilisateurs/'.$cible->getId().'/supprimer', ['_token' => 'faux', 'confirmation' => 'SUPPRIMER']);

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->recharger($cible->getId())->estSupprime());
    }

    public function testDesactiverPuisReactiverUnCompte(): void
    {
        $cible = $this->creerUtilisateur('cible');
        $id = $cible->getId();

        $this->client->loginUser($this->creerUtilisateur('admin', admin: true));
        $jeton = $this->jeton($this->client->request('GET', '/admin/utilisateurs'), "/admin/utilisateurs/$id/actif");

        $this->client->request('POST', "/admin/utilisateurs/$id/actif", ['_token' => $jeton]);
        self::assertResponseRedirects('/admin/utilisateurs');
        self::assertFalse($this->recharger($id)->isActif());

        $this->client->request('POST', "/admin/utilisateurs/$id/actif", ['_token' => $jeton]);
        self::assertTrue($this->recharger($id)->isActif());
    }

    public function testBasculesRefuseesAvecUnJetonInvalide(): void
    {
        $id = $this->creerUtilisateur('cible')->getId();

        $this->client->loginUser($this->creerUtilisateur('admin', admin: true));
        $this->client->request('POST', "/admin/utilisateurs/$id/actif", ['_token' => 'faux']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', "/admin/utilisateurs/$id/admin", ['_token' => 'faux']);
        self::assertResponseStatusCodeSame(403);

        $cible = $this->recharger($id);
        self::assertTrue($cible->isActif());
        self::assertFalse($cible->isAdmin());
    }

    public function testLeDernierAdminNePeutPasSeRetirerLeRole(): void
    {
        $admin = $this->creerUtilisateur('admin', admin: true);
        $id = $admin->getId();

        $this->client->loginUser($admin);
        $jeton = $this->jeton($this->client->request('GET', '/admin/utilisateurs'), "/admin/utilisateurs/$id/admin");
        $this->client->request('POST', "/admin/utilisateurs/$id/admin", ['_token' => $jeton]);

        self::assertContains('Il doit rester au moins un administrateur actif.', $this->flashsApresRedirection('error'));
        self::assertTrue($this->recharger($id)->isAdmin());
    }

    public function testUnNonAdminNAccedePasALAdministration(): void
    {
        $this->client->loginUser($this->creerUtilisateur('grimpeur'));
        $this->client->request('GET', '/admin/utilisateurs');

        self::assertResponseStatusCodeSame(403);
    }

    // --- Mise à jour --------------------------------------------------------

    public function testInstallationRefuseeAvecUnJetonInvalide(): void
    {
        $this->client->loginUser($this->creerUtilisateur('admin', admin: true));
        $this->client->request('POST', '/admin/mise-a-jour/installer', ['_token' => 'faux']);

        self::assertResponseStatusCodeSame(403);
    }

    private function recharger(int $id): User
    {
        $this->em->clear();

        return $this->em->find(User::class, $id);
    }
}
