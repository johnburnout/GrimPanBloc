<?php

namespace App\Repository;

use App\Entity\Croix;
use App\Entity\User;
use App\Entity\Voie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
        $lignes = $this->createQueryBuilder('u')
            ->select('u AS utilisateur')
            ->addSelect(sprintf('(SELECT COUNT(v.id) FROM %s v WHERE v.auteur = u) AS voies', Voie::class))
            ->addSelect(sprintf('(SELECT COUNT(c.id) FROM %s c WHERE c.grimpeur = u) AS croix', Croix::class))
            ->addSelect('CASE WHEN u.supprimeLe IS NULL THEN 0 ELSE 1 END AS HIDDEN estSupprime')
            ->orderBy('estSupprime', 'ASC')
            ->addOrderBy('u.pseudo', 'ASC')
            ->getQuery()->getResult();

        return array_map(fn (array $l) => ['utilisateur' => $l['utilisateur'], 'voies' => (int) $l['voies'], 'croix' => (int) $l['croix']], $lignes);
    }

    /**
     * Comptes actifs (non désactivés, non supprimés), éventuellement limités aux administrateurs.
     *
     * @return list<User>
     */
    public function actifs(bool $adminsSeulement = false): array
    {
        $comptes = $this->createQueryBuilder('u')
            ->andWhere('u.actif = true')
            ->andWhere('u.supprimeLe IS NULL')
            ->orderBy('u.pseudo', 'ASC')
            ->getQuery()->getResult();

        return $adminsSeulement ? array_values(array_filter($comptes, fn (User $u) => $u->isAdmin())) : $comptes;
    }

    public function nombreAdminsActifs(): int
    {
        return \count($this->actifs(true));
    }
}
