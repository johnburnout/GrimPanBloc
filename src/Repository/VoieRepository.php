<?php

namespace App\Repository;

use App\Entity\Secteur;
use App\Entity\Voie;
use App\Enum\SystemeCotation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Voie>
 */
class VoieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Voie::class);
    }

    /**
     * Voies actives, filtrables par secteur, échelle et plage de difficulté,
     * triées par difficulté croissante.
     *
     * @return list<Voie>
     */
    public function rechercher(
        ?Secteur $secteur = null,
        ?SystemeCotation $systeme = null,
        ?int $rangMin = null,
        ?int $rangMax = null,
    ): array {
        $qb = $this->createQueryBuilder('v')
            ->addSelect('p', 's', 'a')
            ->join('v.photo', 'p')
            ->join('p.secteur', 's')
            ->join('v.auteur', 'a')
            ->andWhere('v.active = true')
            ->orderBy('v.systeme', 'ASC')
            ->addOrderBy('v.rang', 'ASC')
            ->addOrderBy('v.nom', 'ASC');

        if ($secteur) {
            $qb->andWhere('s = :secteur')->setParameter('secteur', $secteur);
        }
        if ($systeme) {
            $qb->andWhere('v.systeme = :systeme')->setParameter('systeme', $systeme);
            if (null !== $rangMin) {
                $qb->andWhere('v.rang >= :min')->setParameter('min', $rangMin);
            }
            if (null !== $rangMax) {
                $qb->andWhere('v.rang <= :max')->setParameter('max', $rangMax);
            }
        }

        return $qb->getQuery()->getResult();
    }
}
