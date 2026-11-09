<?php

namespace App\Repository;

use App\Entity\Secteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Secteur>
 */
class SecteurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Secteur::class);
    }

    /**
     * Secteurs par nom, avec leur nombre de voies, en une requête. Les photos ne
     * comptent pas : elles restent dans le secteur, pour être réutilisées, même
     * quand plus aucune voie ne s'en sert.
     *
     * @return list<array{secteur: Secteur, voies: int}>
     */
    public function listeAvecNombreDeVoies(): array
    {
        $lignes = $this->createQueryBuilder('s')
            ->select('s AS secteur', 'COUNT(v.id) AS voies')
            ->leftJoin('s.photos', 'p')
            ->leftJoin('p.voies', 'v')
            ->groupBy('s.id')
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn (array $l): array => ['secteur' => $l['secteur'], 'voies' => (int) $l['voies']], $lignes);
    }
}
