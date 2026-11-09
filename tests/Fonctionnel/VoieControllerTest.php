<?php

namespace App\Tests\Fonctionnel;

use App\Entity\Croix;
use App\Entity\Secteur;

final class VoieControllerTest extends FonctionnelTestCase
{
    // --- Suppression --------------------------------------------------------

    public function testLAuteurSupprimeUneVoieNonValidee(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $voie = $this->creerVoie($auteur);
        $id = $voie->getId();
        $secteurId = $voie->getPhoto()->getSecteur()->getId();

        $this->client->loginUser($auteur);
        $page = $this->client->request('GET', "/voies/$id");
        $this->client->request('POST', "/voies/$id/supprimer", ['_token' => $this->jeton($page, "/voies/$id/supprimer")]);

        self::assertResponseRedirects("/secteurs/$secteurId");
        self::assertContains('Voie supprimée.', $this->flashsApresRedirection('success'));
        self::assertFalse($this->voieExiste($id));
    }

    public function testUnAdminSupprimeLaVoieDUnAutre(): void
    {
        $voie = $this->creerVoie($this->creerUtilisateur('auteur'));
        $id = $voie->getId();

        $this->client->loginUser($this->creerUtilisateur('admin', admin: true));
        $page = $this->client->request('GET', "/voies/$id");
        $this->client->request('POST', "/voies/$id/supprimer", ['_token' => $this->jeton($page, "/voies/$id/supprimer")]);

        self::assertResponseRedirects();
        self::assertFalse($this->voieExiste($id));
    }

    public function testUnAutreGrimpeurNePeutPasSupprimer(): void
    {
        $voie = $this->creerVoie($this->creerUtilisateur('auteur'));
        $id = $voie->getId();

        $this->client->loginUser($this->creerUtilisateur('intrus'));
        $page = $this->client->request('GET', "/voies/$id");
        self::assertCount(0, $page->filter("form[action=\"/voies/$id/supprimer\"]"), 'Le bouton Supprimer ne doit pas être affiché.');

        $this->client->request('POST', "/voies/$id/supprimer", ['_token' => 'peu-importe']);
        self::assertResponseStatusCodeSame(403);
        self::assertTrue($this->voieExiste($id));
    }

    public function testUnVisiteurAnonymeEstEnvoyeVersLaConnexion(): void
    {
        $id = $this->creerVoie($this->creerUtilisateur('auteur'))->getId();

        $this->client->request('POST', "/voies/$id/supprimer", ['_token' => 'x']);

        self::assertResponseRedirects('/connexion');
        self::assertTrue($this->voieExiste($id));
    }

    public function testUnVisiteurAnonymeNePeutRienCreer(): void
    {
        $secteurId = $this->creerVoie($this->creerUtilisateur('auteur'))->getPhoto()->getSecteur()->getId();
        $avant = $this->em->getRepository(Secteur::class)->count();

        foreach (['/secteurs/nouveau', "/voies/nouvelle/$secteurId"] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/connexion', message: $url);
        }
        $this->client->request('POST', '/secteurs/nouveau', ['secteur' => ['nom' => 'Intrus']]);

        self::assertResponseRedirects('/connexion');
        self::assertSame($avant, $this->em->getRepository(Secteur::class)->count());
    }

    public function testSuppressionRefuseeAvecUnJetonInvalide(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $id = $this->creerVoie($auteur)->getId();

        $this->client->loginUser($auteur);
        $this->client->request('POST', "/voies/$id/supprimer", ['_token' => 'faux']);

        self::assertResponseStatusCodeSame(403);
        self::assertTrue($this->voieExiste($id));
    }

    public function testUneVoieValideeExigeLeMotDeConfirmation(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $voie = $this->creerVoie($auteur);
        $this->valider($voie, $this->creerUtilisateur('grimpeur'));
        $id = $voie->getId();

        $this->client->loginUser($auteur);
        $page = $this->client->request('GET', "/voies/$id");
        self::assertSelectorTextContains('#suppression label', 'SUPPRIMER');
        $jeton = $this->jeton($page, "/voies/$id/supprimer");

        $this->client->request('POST', "/voies/$id/supprimer", ['_token' => $jeton, 'confirmation' => 'oui']);
        self::assertResponseRedirects("/voies/$id");
        self::assertContains('Suppression annulée : tapez SUPPRIMER pour confirmer.', $this->flashsApresRedirection('error'));
        self::assertTrue($this->voieExiste($id));

        $this->client->request('POST', "/voies/$id/supprimer", ['_token' => $jeton, 'confirmation' => ' SUPPRIMER ']);
        self::assertResponseRedirects();
        self::assertFalse($this->voieExiste($id));
    }

    // --- Croix --------------------------------------------------------------

    public function testUnGrimpeurPoseModifiePuisRetireSaCroix(): void
    {
        $voie = $this->creerVoie($this->creerUtilisateur('auteur'));
        $grimpeur = $this->creerUtilisateur('grimpeur');
        $id = $voie->getId();

        $this->client->loginUser($grimpeur);
        $jeton = $this->jeton($this->client->request('GET', "/voies/$id"), "/voies/$id/croix");

        $this->client->request('POST', "/voies/$id/croix", ['_token' => $jeton, 'action' => 'poser', 'cotation' => '6b']);
        self::assertResponseRedirects("/voies/$id");
        self::assertContains('Bravo ! Voie cochée comme réussie.', $this->flashsApresRedirection('success'));
        self::assertSame('6b', $this->croixDe($id, $grimpeur->getId())?->getCotation());

        $this->client->request('POST', "/voies/$id/croix", ['_token' => $jeton, 'action' => 'modifier', 'cotation' => '6c']);
        self::assertContains('Cotation proposée modifiée : 6c.', $this->flashsApresRedirection('success'));
        self::assertSame('6c', $this->croixDe($id, $grimpeur->getId())?->getCotation());

        $this->client->request('POST', "/voies/$id/croix", ['_token' => $jeton, 'action' => 'retirer']);
        self::assertContains('Croix retirée.', $this->flashsApresRedirection('success'));
        self::assertNull($this->croixDe($id, $grimpeur->getId()));
    }

    public function testUnGrimpeurDoitProposerUneCotation(): void
    {
        $id = $this->creerVoie($this->creerUtilisateur('auteur'))->getId();
        $grimpeur = $this->creerUtilisateur('grimpeur');

        $this->client->loginUser($grimpeur);
        $jeton = $this->jeton($this->client->request('GET', "/voies/$id"), "/voies/$id/croix");
        $this->client->request('POST', "/voies/$id/croix", ['_token' => $jeton, 'action' => 'poser', 'cotation' => 'n-importe']);

        self::assertContains('Choisissez la cotation que vous proposez.', $this->flashsApresRedirection('error'));
        self::assertNull($this->croixDe($id, $grimpeur->getId()));
    }

    public function testLAuteurPoseUneCroixSansCotation(): void
    {
        $auteur = $this->creerUtilisateur('auteur');
        $id = $this->creerVoie($auteur)->getId();

        $this->client->loginUser($auteur);
        $jeton = $this->jeton($this->client->request('GET', "/voies/$id"), "/voies/$id/croix");
        $this->client->request('POST', "/voies/$id/croix", ['_token' => $jeton, 'action' => 'poser']);

        self::assertResponseRedirects("/voies/$id");
        $croix = $this->croixDe($id, $auteur->getId());
        self::assertNotNull($croix);
        self::assertNull($croix->getCotation());
    }

    public function testCroixRefuseeAvecUnJetonInvalide(): void
    {
        $id = $this->creerVoie($this->creerUtilisateur('auteur'))->getId();
        $grimpeur = $this->creerUtilisateur('grimpeur');

        $this->client->loginUser($grimpeur);
        $this->client->request('POST', "/voies/$id/croix", ['_token' => 'faux', 'action' => 'poser', 'cotation' => '6a']);

        self::assertResponseStatusCodeSame(403);
        self::assertNull($this->croixDe($id, $grimpeur->getId()));
    }

    public function testActionDeCroixInconnueRefusee(): void
    {
        $id = $this->creerVoie($this->creerUtilisateur('auteur'))->getId();

        $this->client->loginUser($this->creerUtilisateur('grimpeur'));
        $jeton = $this->jeton($this->client->request('GET', "/voies/$id"), "/voies/$id/croix");
        // « modifier » sans croix existante
        $this->client->request('POST', "/voies/$id/croix", ['_token' => $jeton, 'action' => 'modifier', 'cotation' => '6a']);

        self::assertResponseStatusCodeSame(403);
    }

    private function croixDe(int $voieId, int $userId): ?Croix
    {
        $this->em->clear();

        return $this->em->getRepository(Croix::class)->findOneBy(['voie' => $voieId, 'grimpeur' => $userId]);
    }
}
