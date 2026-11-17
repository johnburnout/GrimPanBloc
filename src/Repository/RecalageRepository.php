<?php

namespace App\Repository;

use App\Entity\Photo;
use App\Entity\Recalage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recalage>
 */
class RecalageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recalage::class);
    }

    public function entre(Photo $source, Photo $cible): ?Recalage
    {
        return $this->findOneBy(['photoSource' => $source, 'photoCible' => $cible]);
    }

    /**
     * Recalages qui partent de ces photos, du plus récemment mis à jour au plus ancien.
     *
     * @param list<Photo> $photos
     *
     * @return list<Recalage>
     */
    public function depuis(array $photos): array
    {
        if ([] === $photos) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->addSelect('c', 'i')
            ->join('r.photoCible', 'c')
            ->leftJoin('r.voiesIgnorees', 'i')
            ->andWhere('r.photoSource IN (:photos)')->setParameter('photos', $photos)
            ->orderBy('r.majLe', 'DESC')->addOrderBy('r.id', 'DESC')
            ->getQuery()->getResult();
    }
}
