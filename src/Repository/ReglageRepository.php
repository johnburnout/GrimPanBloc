<?php

namespace App\Repository;

use App\Entity\Reglage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reglage>
 */
class ReglageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reglage::class);
    }

    public function valeur(string $cle, ?string $defaut = null): ?string
    {
        return $this->find($cle)?->getValeur() ?? $defaut;
    }

    /** Enregistre la valeur (persist + flush à la charge de l'appelant). */
    public function definir(string $cle, ?string $valeur): void
    {
        $reglage = $this->find($cle);
        if (!$reglage) {
            $reglage = new Reglage($cle);
            $this->getEntityManager()->persist($reglage);
        }
        $reglage->setValeur($valeur);
    }
}
