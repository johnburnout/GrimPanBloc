<?php

namespace App\Tests\Fonctionnel;

use App\Mail\ReglagesSmtp;
use App\Repository\ReglageRepository;
use App\Site\IdentiteClub;
use App\Site\ReglageHttps;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Page Administration → Réglages. Chaque section a son formulaire ; les champs
 * sont désignés par leur nom court (« hote », « nom_club »…), quel que soit le
 * préfixe que leur donne le formulaire.
 */
final class AdminReglagesTest extends FonctionnelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->creerUtilisateur('admin', admin: true));
    }

    // --- HTTPS --------------------------------------------------------------

    public function testHttpsNePeutPasEtreActiveDepuisUnePageEnHttp(): void
    {
        $this->soumettre('Sécurité de la connexion', ['forcer_https' => '1']);

        $this->assertErreurAffichee('Ouvrez d\'abord cette page en HTTPS');
        self::assertNotSame('1', $this->reglage(ReglageHttps::CLE));
    }

    public function testHttpsPeutEtreDesactive(): void
    {
        $this->soumettre('Sécurité de la connexion', ['forcer_https' => false]);

        self::assertResponseRedirects('/admin/reglages');
        self::assertContains('Redirection vers HTTPS désactivée.', $this->flashsApresRedirection('success'));
        self::assertSame('0', $this->reglage(ReglageHttps::CLE));
    }

    /** Activé puis désactivé depuis une page en HTTPS : chaque changement s'applique aussitôt. */
    public function testHttpsActiveRedirigeLeHttpPuisSeDesactive(): void
    {
        $this->client->request('GET', 'https://localhost/admin/reglages');
        $this->soumettre('Sécurité de la connexion', ['forcer_https' => '1']);
        self::assertResponseRedirects('/admin/reglages');

        $this->client->request('GET', 'http://localhost/secteurs');
        self::assertResponseRedirects('https://localhost/secteurs', 301);
        $this->client->request('GET', 'https://localhost/secteurs');
        self::assertResponseHeaderSame('Strict-Transport-Security', 'max-age=2592000');

        $this->soumettre('Sécurité de la connexion', ['forcer_https' => false]);
        // Sans effacement du cache, la redirection survivrait à la désactivation.
        $this->client->request('GET', 'http://localhost/secteurs');
        self::assertResponseIsSuccessful();
    }

    /** La valeur vient du cache : pas de requête SQL à chaque page. */
    public function testLeReglageHttpsEstLuDansLeCache(): void
    {
        $reglage = static::getContainer()->get(ReglageHttps::class);
        self::assertFalse($reglage->actif());

        // Écriture directe en base, sans passer par ReglageHttps::definir()
        static::getContainer()->get(ReglageRepository::class)->definir(ReglageHttps::CLE, '1');
        $this->em->flush();

        self::assertFalse($reglage->actif(), 'Valeur en cache, base non relue.');
        $reglage->definir(true);
        self::assertTrue($reglage->actif());
    }

    // --- Club ---------------------------------------------------------------

    public function testEnregistrerLeNomDuClub(): void
    {
        $this->soumettre('Identité de la salle', ['nom_club' => '  Les Grimpeurs du Causse ']);

        self::assertResponseRedirects('/admin/reglages');
        self::assertContains('Identité du club enregistrée.', $this->flashsApresRedirection('success'));
        self::assertSame('Les Grimpeurs du Causse', $this->reglage('club.nom'));
        self::assertSame('Les Grimpeurs du Causse', $this->section('Identité de la salle')->filter('input[type="text"]')->attr('value'));
    }

    /** Nom et logo modifiés : l'en-tête de la page suivante les montre aussitôt (cache effacé). */
    public function testLEnTeteSuitLesChangementsDuNomEtDuLogo(): void
    {
        $png = tempnam(sys_get_temp_dir(), 'logo');
        imagepng(imagecreatetruecolor(60, 40), $png);
        $this->client->request('GET', '/secteurs');
        self::assertSelectorNotExists('img.logo-club');

        $this->soumettre('Identité de la salle', ['nom_club' => 'Club du Causse'], ['logo' => new UploadedFile($png, 'logo.png', 'image/png', null, true)]);
        $logo = (string) $this->reglage('club.logo');
        try {
            self::assertFileExists(static::getContainer()->getParameter('kernel.project_dir').'/public/'.$logo);
            $this->client->request('GET', '/secteurs');
            self::assertSelectorExists('img.logo-club[alt="Club du Causse"][src$="'.basename($logo).'"]');

            $this->soumettre('Identité de la salle', ['nom_club' => 'Club du Causse', 'supprimer_logo' => '1']);
            $this->client->request('GET', '/secteurs');
            self::assertSelectorNotExists('img.logo-club');
        } finally {
            @unlink(static::getContainer()->getParameter('kernel.project_dir').'/public/'.$logo);
        }
    }

    /** Nom et logo viennent du cache : pas de requête SQL à chaque page. */
    public function testLIdentiteDuClubEstLueDansLeCache(): void
    {
        $club = static::getContainer()->get(IdentiteClub::class);
        self::assertSame('', $club->nom());

        // Écriture directe en base, sans passer par IdentiteClub
        static::getContainer()->get(ReglageRepository::class)->definir('club.nom', 'Autre nom');
        $this->em->flush();

        self::assertSame('', $club->nom(), 'Valeur en cache, base non relue.');
        $club->definirNom('Club du Causse');
        self::assertSame('Club du Causse', $club->nom());
    }

    public function testUnLogoQuiNEstPasUneImageEstRefuse(): void
    {
        $faux = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($faux, '<?php echo "pas une image";');

        $this->soumettre('Identité de la salle', ['nom_club' => 'Club'], ['logo' => new UploadedFile($faux, 'logo.png', 'image/png', null, true)]);

        $this->assertErreurAffichee('PNG, JPEG ou WebP');
        self::assertSame('', (string) $this->reglage('club.logo'));
    }

    public function testUnLogoTropGrandEstRefuse(): void
    {
        $this->soumettre('Identité de la salle', ['nom_club' => 'Club'], ['logo' => $this->pngGeant(10_000, 10_000)]);

        $this->assertErreurAffichee('Le logo dépasse 25 mégapixels');
        self::assertSame('', (string) $this->reglage('club.logo'));
    }

    // --- SMTP ---------------------------------------------------------------

    public function testEnregistrerLesReglagesSmtpPuisGarderEtEffacerLeMotDePasse(): void
    {
        $smtp = static::getContainer()->get(ReglagesSmtp::class);

        $this->soumettre('Serveur d\'envoi', $this->smtpValide() + ['mot_de_passe' => 'secret 1']);
        self::assertResponseRedirects('/admin/reglages');
        self::assertContains('Réglages SMTP enregistrés.', $this->flashsApresRedirection('success'));
        self::assertSame([
            'hote' => 'smtp.exemple.org', 'port' => 465, 'chiffrement' => 'ssl', 'utilisateur' => 'club',
            'expediteur' => 'contact@exemple.org', 'expediteur_nom' => 'Le club', 'mot_de_passe_defini' => true,
        ], $smtp->lire());
        self::assertSame('secret 1', $smtp->motDePasse());

        // Mot de passe laissé vide : il est conservé
        $this->soumettre('Serveur d\'envoi', ['hote' => 'smtp2.exemple.org', 'mot_de_passe' => '']);
        self::assertResponseRedirects();
        self::assertSame('smtp2.exemple.org', $smtp->lire()['hote']);
        self::assertSame('secret 1', $smtp->motDePasse());

        $this->soumettre('Serveur d\'envoi', ['effacer_mot_de_passe' => '1']);
        self::assertResponseRedirects();
        self::assertSame('', $smtp->motDePasse());
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function saisiesSmtpInvalides(): iterable
    {
        yield 'serveur' => [['hote' => 'smtp exemple;org'], 'Nom de serveur SMTP invalide.'];
        yield 'expéditeur' => [['expediteur' => 'pas-une-adresse'], 'Adresse d\'expédition invalide.'];
        yield 'port nul' => [['port' => '0'], 'Port invalide.'];
        yield 'port trop grand' => [['port' => '70000'], 'Port invalide.'];
    }

    /** @param array<string, string> $saisie */
    #[\PHPUnit\Framework\Attributes\DataProvider('saisiesSmtpInvalides')]
    public function testSaisieSmtpInvalide(array $saisie, string $message): void
    {
        $this->soumettre('Serveur d\'envoi', $saisie + $this->smtpValide());

        $this->assertErreurAffichee($message);
        self::assertSame('', static::getContainer()->get(ReglagesSmtp::class)->lire()['hote']);
    }

    // --- Mail de test -------------------------------------------------------

    public function testMailDeTestVersUneAdresseInvalide(): void
    {
        $this->soumettre('Mail de test', ['destinataire' => 'personne']);

        $this->assertErreurAffichee('Adresse du destinataire invalide.');
    }

    public function testMailDeTestSansServeurConfigure(): void
    {
        $this->soumettre('Mail de test', ['destinataire' => 'moi@exemple.org']);

        $this->assertErreurAffichee('Échec de l\'envoi : Le serveur SMTP n\'est pas configuré');
        self::assertSame('moi@exemple.org', $this->section('Mail de test')->filter('input[type="email"]')->attr('value'), 'La saisie est conservée.');
    }

    // --- Envoi groupé -------------------------------------------------------

    public function testEnvoiGroupeSansObjetNiMessage(): void
    {
        $this->soumettre('Envoyer un message', ['objet' => '  ', 'corps' => '']);

        $this->assertErreurAffichee('obligatoire');
    }

    public function testEnvoiGroupeMessageTropLong(): void
    {
        $this->soumettre('Envoyer un message', ['objet' => 'Info', 'corps' => str_repeat('a', 20001)]);

        $this->assertErreurAffichee('trop long');
    }

    public function testEnvoiGroupeSansServeurConfigureConserveLaSaisie(): void
    {
        $this->soumettre('Envoyer un message', ['cible' => 'admins', 'objet' => 'Fermeture', 'corps' => 'Bonjour {pseudo}']);

        $this->assertErreurAffichee('Échec de l\'envoi : Le serveur SMTP n\'est pas configuré');
        $section = $this->section('Envoyer un message');
        self::assertSame('Fermeture', $section->filter('input[type="text"]')->attr('value'));
        self::assertSame('Bonjour {pseudo}', $section->filter('textarea')->text());
        self::assertSame('admins', $section->filter('input[type="radio"]:checked')->attr('value'));
    }

    // --- Accès --------------------------------------------------------------

    public function testJetonInvalideRefuse(): void
    {
        $this->soumettre('Identité de la salle', ['_token' => 'faux', 'nom_club' => 'Pirate']);

        self::assertResponseStatusCodeSame(422);
        $this->assertErreurAffichee('Le formulaire a expiré');
        self::assertNull($this->reglage('club.nom'));
    }

    public function testUnNonAdminNAccedePasAuxReglages(): void
    {
        $this->client->loginUser($this->creerUtilisateur('grimpeur'));
        $this->client->request('GET', '/admin/reglages');

        self::assertResponseStatusCodeSame(403);
    }

    // ------------------------------------------------------------------------

    /** @return array<string, string> */
    private function smtpValide(): array
    {
        return ['hote' => 'smtp.exemple.org', 'port' => '465', 'chiffrement' => 'ssl', 'utilisateur' => 'club',
            'expediteur' => 'contact@exemple.org', 'expediteur_nom' => 'Le club'];
    }

    private function section(string $titre): \Symfony\Component\DomCrawler\Crawler
    {
        $crawler = $this->client->getCrawler();
        // Page affichée (réglages, ou réponse à un formulaire invalide) ; sinon on l'ouvre
        if ($this->client->getHistory()->isEmpty() || 0 === $crawler->filter('section h2')->count()) {
            $crawler = $this->client->request('GET', '/admin/reglages');
        }
        $section = $crawler->filter('section')->reduce(fn ($s) => str_contains($s->filter('h2')->text(''), $titre));
        self::assertCount(1, $section, "Section « $titre » introuvable.");

        return $section;
    }

    /**
     * Soumet le formulaire d'une section comme le navigateur, en remplaçant
     * certains champs (false = case décochée). Les champs désactivés (case HTTPS
     * en HTTP) sont envoyés quand même, pour tester les garde-fous du serveur.
     *
     * @param array<string, string|false> $valeurs
     * @param array<string, UploadedFile> $fichiers
     */
    private function soumettre(string $titre, array $valeurs, array $fichiers = []): void
    {
        $this->client->request('GET', '/admin/reglages');
        $form = $this->section($titre)->filter('form')->form();
        $noms = array_keys($form->all());
        $trouver = function (string $court) use ($noms): string {
            foreach ($noms as $nom) {
                if ($nom === $court || str_ends_with($nom, "[$court]")) {
                    return $nom;
                }
            }
            self::fail("Champ « $court » absent du formulaire.");
        };

        $champs = $form->getValues();
        foreach ($valeurs as $court => $valeur) {
            $nom = $trouver($court);
            if (false === $valeur) {
                unset($champs[$nom]);
            } else {
                $champs[$nom] = $valeur;
            }
        }
        parse_str(http_build_query($champs), $donnees);

        $envois = [];
        foreach ($fichiers as $court => $fichier) {
            $chemin = preg_split('/[\[\]]+/', $trouver($court), -1, PREG_SPLIT_NO_EMPTY);
            $cible = &$envois;
            foreach ($chemin as $cle) {
                $cible = &$cible[$cle];
            }
            $cible = $fichier;
            unset($cible);
        }

        $this->client->request($form->getMethod(), $form->getUri(), $donnees, $envois);
    }

    private function assertErreurAffichee(string $message): void
    {
        self::assertFalse($this->client->getResponse()->isRedirect(), 'La saisie invalide ne doit pas être enregistrée.');
        self::assertStringContainsString($message, $this->client->getCrawler()->filter('main, body')->first()->text());
    }

    private function reglage(string $cle): ?string
    {
        $this->em->clear();

        return static::getContainer()->get(ReglageRepository::class)->valeur($cle);
    }
}
