<?php

namespace App\Tests\Fonctionnel;

use App\Entity\User;
use App\Repository\UserRepository;

/** Requêtes de l'administration des comptes, sur la base de test. */
final class UserRepositoryTest extends FonctionnelTestCase
{
    private UserRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = static::getContainer()->get(UserRepository::class);

        $this->creerUtilisateur('alice', admin: true);
        $this->creerUtilisateur('bruno');
        $this->creerUtilisateur('chloe', admin: true)->setActif(false);
        $this->creerUtilisateur('denis', admin: true)->supprimer();
        $this->creerUtilisateur('emma');
        $this->creerUtilisateur('farid', admin: true);
        $this->em->flush();
        $this->em->clear();
    }

    public function testComptesActifs(): void
    {
        self::assertSame(['alice', 'bruno', 'emma', 'farid'], $this->pseudos($this->repo->actifs()));
        self::assertSame(['alice', 'farid'], $this->pseudos($this->repo->actifs(true)));
    }

    public function testNombresDeComptesActifs(): void
    {
        self::assertSame(4, $this->repo->nombreActifs());
        self::assertSame(2, $this->repo->nombreAdminsActifs());
    }

    public function testListeAdministrationAvecLesComptesSupprimesALaFin(): void
    {
        $auteur = $this->repo->findOneBy(['pseudo' => 'bruno']);
        $voie = $this->creerVoie($auteur);
        $this->creerVoie($auteur, 'Deuxième');
        $this->valider($voie, $this->repo->findOneBy(['pseudo' => 'emma']));

        $lignes = $this->repo->listeAdministration();

        self::assertSame(['alice', 'bruno', 'chloe', 'emma', 'farid', 'denis'], $this->pseudos(array_column($lignes, 'utilisateur')));
        $bruno = $lignes[1];
        self::assertSame([2, 0], [$bruno['voies'], $bruno['croix']]);
        self::assertSame([0, 1], [$lignes[3]['voies'], $lignes[3]['croix']]);

        self::assertSame($bruno, $this->repo->ligneAdministration($bruno['utilisateur']));
    }

    /**
     * @param list<User> $comptes
     *
     * @return list<string>
     */
    private function pseudos(array $comptes): array
    {
        return array_map(fn (User $u) => $u->getPseudo(), $comptes);
    }
}
