<?php

namespace App\Repository;

use App\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * Photos qu'aucune voie n'utilise (restées après la suppression d'une voie ou
     * un changement de photo), par secteur puis de la plus récente à la plus ancienne.
     *
     * @return list<Photo>
     */
    public function nonUtilisees(): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('s', 'a')
            ->join('p.secteur', 's')
            ->join('p.auteur', 'a')
            ->leftJoin('p.voies', 'v')
            ->andWhere('v.id IS NULL')
            ->orderBy('s.nom', 'ASC')
            ->addOrderBy('p.creeLe', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
