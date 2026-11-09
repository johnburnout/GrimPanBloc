<?php

namespace App\Service;

use App\Entity\Croix;
use App\Entity\User;
use App\Entity\Voie;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Croix d'un grimpeur sur une voie (« j'ai réussi cette voie »).
 * La cotation affichée de la voie est recalculée à chaque changement
 * (Voie::recalculerCotation()).
 */
final class GestionCroix
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Pose la croix du grimpeur, ou met à jour la cotation qu'il propose.
     * L'auteur propose sa cotation sur la voie : sa croix n'en porte pas.
     *
     * @throws \InvalidArgumentException si un validateur ne propose pas une cotation de l'échelle de la voie
     */
    public function cocher(Voie $voie, User $grimpeur, string $cotationProposee): Croix
    {
        $cotation = null;
        if ($voie->getAuteur() !== $grimpeur) {
            $cotation = $voie->getSysteme()->cotation($cotationProposee)->value
                ?? throw new \InvalidArgumentException('Choisissez la cotation que vous proposez.');
        }

        $croix = $voie->getCroixDe($grimpeur) ?? new Croix($voie, $grimpeur);
        $croix->setCotation($cotation);
        $voie->ajouterCroix($croix);
        $this->em->persist($croix);
        $this->em->flush();

        return $croix;
    }

    /** Retire la croix du grimpeur. Renvoie false s'il n'en avait pas. */
    public function retirer(Voie $voie, User $grimpeur): bool
    {
        $croix = $voie->getCroixDe($grimpeur);
        if (null === $croix) {
            return false;
        }
        $voie->retirerCroix($croix);
        $this->em->remove($croix);
        $this->em->flush();

        return true;
    }
}
