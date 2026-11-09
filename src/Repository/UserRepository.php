<?php

namespace App\Repository;

use App\Entity\Croix;
use App\Entity\User;
use App\Entity\Voie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Comptes pour la liste d'administration, avec leurs nombres de voies et de croix.
     * Les comptes supprimés sont à la fin.
     *
     * @return list<array{utilisateur: User, voies: int, croix: int}>
     */
    public function listeAdministration(): array
    {
        $lignes = $this->administration()
            ->addSelect('CASE WHEN u.supprimeLe IS NULL THEN 0 ELSE 1 END AS HIDDEN estSupprime')
            ->orderBy('estSupprime', 'ASC')
            ->addOrderBy('u.pseudo', 'ASC')
            ->getQuery()->getResult();

        return array_map($this->ligne(...), $lignes);
    }

    /** @return array{utilisateur: User, voies: int, croix: int}|null */
    public function ligneAdministration(User $utilisateur): ?array
    {
        $ligne = $this->administration()
            ->andWhere('u = :u')->setParameter('u', $utilisateur)
            ->getQuery()->getOneOrNullResult();

        return null === $ligne ? null : $this->ligne($ligne);
    }

    /**
     * Comptes actifs (non désactivés, non supprimés), éventuellement limités aux administrateurs.
     *
     * @return list<User>
     */
    public function actifs(bool $adminsSeulement = false): array
    {
        return $this->qbActifs($adminsSeulement)->orderBy('u.pseudo', 'ASC')->getQuery()->getResult();
    }

    public function nombreActifs(bool $adminsSeulement = false): int
    {
        return (int) $this->qbActifs($adminsSeulement)->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
    }

    public function nombreAdminsActifs(): int
    {
        return $this->nombreActifs(true);
    }

    private function qbActifs(bool $adminsSeulement): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.actif = true')
            ->andWhere('u.supprimeLe IS NULL');
        if ($adminsSeulement) {
            // Colonne JSON : ["ROLE_ADMIN", …]
            $qb->andWhere('u.roles LIKE :role')->setParameter('role', '%"ROLE_ADMIN"%');
        }

        return $qb;
    }

    private function administration(): QueryBuilder
    {
        return $this->createQueryBuilder('u')
            ->select('u AS utilisateur')
            ->addSelect(\sprintf('(SELECT COUNT(v.id) FROM %s v WHERE v.auteur = u) AS voies', Voie::class))
            ->addSelect(\sprintf('(SELECT COUNT(c.id) FROM %s c WHERE c.grimpeur = u) AS croix', Croix::class));
    }

    /**
     * @param array{utilisateur: User, voies: int|string, croix: int|string} $l ligne brute de la requête
     *
     * @return array{utilisateur: User, voies: int, croix: int}
     */
    private function ligne(array $l): array
    {
        return ['utilisateur' => $l['utilisateur'], 'voies' => (int) $l['voies'], 'croix' => (int) $l['croix']];
    }
}
