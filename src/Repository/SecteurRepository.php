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

    /**
     * Nombre de voies, de photos (utilisées ou non) et de croix du secteur.
     *
     * @return array{voies: int, photos: int, croix: int}
     */
    public function contenu(Secteur $secteur): array
    {
        $c = $this->getEntityManager()->getConnection();

        return [
            'voies' => (int) $c->fetchOne('SELECT COUNT(*) FROM voie v JOIN photo p ON p.id = v.photo_id WHERE p.secteur_id = ?', [$secteur->getId()]),
            'photos' => (int) $c->fetchOne('SELECT COUNT(*) FROM photo WHERE secteur_id = ?', [$secteur->getId()]),
            'croix' => (int) $c->fetchOne('SELECT COUNT(*) FROM croix c JOIN voie v ON v.id = c.voie_id JOIN photo p ON p.id = v.photo_id WHERE p.secteur_id = ?', [$secteur->getId()]),
        ];
    }
}
