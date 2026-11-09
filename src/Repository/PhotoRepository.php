<?php

namespace App\Repository;

use App\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Photo>
 */
class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    /**
     * Une page des photos qu'aucune voie n'utilise (restées après la suppression d'une
     * voie ou un changement de photo), par secteur puis de la plus récente à la plus
     * ancienne, puis par identifiant pour un ordre stable d'une page à l'autre.
     *
     * @return list<Photo>
     */
    public function nonUtilisees(int $premiere, int $nombre): array
    {
        return $this->requeteNonUtilisees()
            ->addSelect('s', 'a')
            ->join('p.secteur', 's')
            ->join('p.auteur', 'a')
            ->orderBy('s.nom', 'ASC')
            ->addOrderBy('p.creeLe', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setFirstResult($premiere)
            ->setMaxResults($nombre)
            ->getQuery()
            ->getResult();
    }

    public function compterNonUtilisees(): int
    {
        return (int) $this->requeteNonUtilisees()
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function requeteNonUtilisees(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.voies', 'v')
            ->andWhere('v.id IS NULL');
    }
}
