<?php

namespace App\Repository;

use App\Dto\CriteresVoies;
use App\Entity\Secteur;
use App\Entity\Voie;
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
     * Voies actives selon les critères d'affichage : secteur (imposé par $secteur
     * sur la page d'un secteur), échelle, plage de difficulté, tri et ordre.
     *
     * @return list<Voie>
     */
    public function rechercher(CriteresVoies $criteres = new CriteresVoies(), ?Secteur $secteur = null): array
    {
        $qb = $this->createQueryBuilder('v')
            ->addSelect('p', 's', 'a')
            ->join('v.photo', 'p')
            ->join('p.secteur', 's')
            ->join('v.auteur', 'a')
            ->andWhere('v.active = true');

        if ($secteur) {
            $qb->andWhere('s = :secteur')->setParameter('secteur', $secteur);
        } elseif (null !== $criteres->secteurId()) {
            $qb->andWhere('s.id = :secteur')->setParameter('secteur', $criteres->secteurId());
        }
        if ($systeme = $criteres->systemeCotation()) {
            $qb->andWhere('v.systeme = :systeme')->setParameter('systeme', $systeme);
            if ($min = $criteres->cotationMin()) {
                $qb->andWhere('v.rang >= :min')->setParameter('min', $min->rang());
            }
            if ($max = $criteres->cotationMax()) {
                $qb->andWhere('v.rang <= :max')->setParameter('max', $max->rang());
            }
        }

        $ordre = 'desc' === $criteres->ordre ? 'DESC' : 'ASC';
        $parCotation = fn () => $qb->addOrderBy('v.systeme', 'ASC')->addOrderBy('v.rang', $ordre);
        match ($criteres->tri) {
            'nom' => $qb->orderBy('v.nom', $ordre),
            'secteur' => $qb->orderBy('s.nom', $ordre)->addOrderBy('v.systeme', 'ASC')->addOrderBy('v.rang', 'ASC'),
            'ouvreur' => $qb->orderBy('a.pseudo', $ordre)->addOrderBy('v.systeme', 'ASC')->addOrderBy('v.rang', 'ASC'),
            default => $parCotation(),
        };
        $qb->addOrderBy('v.nom', 'ASC')->addOrderBy('v.id', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
