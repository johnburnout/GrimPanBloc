<?php

namespace App\Service;

use App\Entity\Photo;
use App\Entity\Prise;
use App\Entity\Recalage;
use App\Entity\User;
use App\Entity\Voie;
use App\Repository\RecalageRepository;
use App\Repository\VoieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Changement de photo d'une voie et propositions aux autres voies de l'ancienne photo.
 *
 * Changer de photo ne change pas la voie : ses prises gardent leur nombre et leur
 * type, seules leur position et leur taille sont réajustées (changerPhoto()). C'est
 * vrai pour toutes les voies, validées ou non ; la cotation et les croix restent.
 *
 * 1. Un créateur change la photo de sa voie et replace ses prises : les paires
 *    (ancienne position, nouvelle position) sont enregistrées dans le Recalage
 *    ancienne photo → nouvelle photo, qui en déduit sa matrice.
 * 2. Les créateurs des autres voies de l'ancienne photo voient sur l'accueil
 *    qu'une nouvelle photo est proposée, avec leurs prises replacées d'après le recalage.
 * 3. S'ils l'adoptent (après avoir ajusté les prises), leurs paires affinent le recalage ;
 *    sinon ils peuvent l'ignorer.
 */
final class GestionRecalage
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RecalageRepository $recalages,
        private readonly VoieRepository $voies,
        private readonly PhotoUploader $uploader,
    ) {
    }

    /**
     * Positions des prises enregistrées de la voie, avant modification.
     *
     * @return array<int, array{float, float}> identifiant de la prise → [x, y]
     */
    public static function positions(Voie $voie): array
    {
        $positions = [];
        foreach ($voie->getPrises() as $p) {
            if (null !== $p->getId()) {
                $positions[$p->getId()] = [$p->getX(), $p->getY()];
            }
        }

        return $positions;
    }

    /**
     * Après l'enregistrement d'une voie : si sa photo a changé, enregistre les paires
     * formées par ses prises (position avant / position choisie sur la nouvelle photo).
     *
     * @param array<int, array{float, float}> $positionsAvant voir positions()
     *
     * @return Recalage|null le recalage mis à jour, null si la photo n'a pas changé
     *                       ou qu'aucune prise d'origine n'a été gardée
     */
    public function noterChangementPhoto(Voie $voie, ?Photo $ancienne, array $positionsAvant): ?Recalage
    {
        $nouvelle = $voie->getPhoto();
        if (null === $ancienne || null === $nouvelle || $ancienne === $nouvelle) {
            return null;
        }

        $paires = [];
        foreach ($voie->getPrises() as $p) {
            $o = $p->getOrigine();
            // Chaque prise d'origine ne compte qu'une fois (identifiant inconnu ou répété : ignoré)
            if (null !== $o && isset($positionsAvant[$o])) {
                $paires[] = [$positionsAvant[$o][0], $positionsAvant[$o][1], $p->getX(), $p->getY()];
                unset($positionsAvant[$o]);
            }
        }
        if ([] === $paires) {
            return null;
        }

        $recalage = $this->recalages->entre($ancienne, $nouvelle) ?? new Recalage($ancienne, $nouvelle);
        $recalage->enregistrerPaires($voie, $paires);
        $this->em->persist($recalage);
        $this->em->flush();

        return $recalage;
    }

    /**
     * Autres voies de la photo à qui la nouvelle photo va être proposée.
     *
     * @return list<Voie>
     */
    public function voiesConcernees(Recalage $recalage, ?Voie $sauf = null): array
    {
        return array_values(array_filter(
            $this->voies->findBy(['photo' => $recalage->getPhotoSource()]),
            fn (Voie $v) => $v !== $sauf && $this->estProposable($v, $recalage),
        ));
    }

    /**
     * Nouvelles photos proposées pour les voies du grimpeur (la plus récente par voie).
     *
     * @return list<array{voie: Voie, recalage: Recalage}>
     */
    public function propositionsPour(User $user): array
    {
        $voies = $this->voies->findBy(['auteur' => $user], ['nom' => 'ASC']);
        $photos = [];
        foreach ($voies as $v) {
            $photos[$v->getPhoto()->getId()] = $v->getPhoto();
        }
        $recalages = $this->recalages->depuis(array_values($photos));

        $propositions = [];
        foreach ($voies as $v) {
            foreach ($recalages as $r) {
                if ($this->estProposable($v, $r)) {
                    $propositions[] = ['voie' => $v, 'recalage' => $r];
                    break;
                }
            }
        }

        return $propositions;
    }

    /** Le recalage part de la photo de la voie, dont le créateur ne l'a pas refusé. */
    public function estProposable(Voie $voie, Recalage $recalage): bool
    {
        return $voie->getPhoto() === $recalage->getPhotoSource()
            && !$recalage->getVoiesIgnorees()->contains($voie);
    }

    /**
     * Passe la voie sur une autre photo du secteur, ou sur une photo envoyée, sans toucher
     * à son tracé : une prise ajustée dans l'éditeur prend la position et la taille
     * choisies ; les autres (et toute prise ajoutée, supprimée ou d'un autre type dans
     * l'envoi) sont ignorées : elles gardent la position proposée par le recalage
     * existant vers cette photo, ou à défaut leur position actuelle.
     *
     * @param iterable<Prise> $ajustees prises renvoyées par l'éditeur (origine = prise de la voie)
     *
     * @return Recalage|null le recalage mis à jour (null si aucune prise)
     */
    public function changerPhoto(Voie $voie, Photo|UploadedFile $cible, User $auteur, iterable $ajustees): ?Recalage
    {
        $ancienne = $voie->getPhoto();
        if ($cible instanceof UploadedFile) {
            $cible = $this->uploader->upload($cible, $ancienne->getSecteur(), $auteur);
            $this->em->persist($cible);
        }

        $choisies = [];
        foreach ($ajustees as $p) {
            if (null !== $p->getOrigine()) {
                $choisies[$p->getOrigine()] ??= $p;
            }
        }
        $proposees = [];
        $existant = null !== $cible->getId() ? $this->recalages->entre($ancienne, $cible) : null;
        foreach (null !== $existant ? $this->prisesProposees($voie, $existant) : [] as $p) {
            $proposees[$p->getOrigine()] = $p;
        }

        $positionsAvant = self::positions($voie);
        foreach ($voie->getPrises() as $prise) {
            $choix = $choisies[$prise->getId()] ?? $proposees[$prise->getId()] ?? $prise;
            $prise->setX($choix->getX())->setY($choix->getY())->setRayon($choix->getRayon())
                ->setOrigine($prise->getId());
        }
        $voie->setPhoto($cible);
        $this->em->flush();

        return $this->noterChangementPhoto($voie, $ancienne, $positionsAvant);
    }

    /**
     * Prises de la voie replacées sur la nouvelle photo (non enregistrées). Chacune garde
     * l'identifiant de la prise d'origine, pour que l'enregistrement affine le recalage.
     *
     * @return list<Prise>
     */
    public function prisesProposees(Voie $voie, Recalage $recalage): array
    {
        $modele = $recalage->modele();
        $cible = $recalage->getPhotoCible();
        // Rayon en % de la largeur ; les y sont en % de la hauteur : rapport hauteur / largeur
        $rapport = $cible->getLargeur() > 0 ? $cible->getHauteur() / $cible->getLargeur() : 1.0;

        $prises = [];
        foreach ($voie->getPrises() as $p) {
            [$x, $y] = $modele->transformer($p->getX(), $p->getY());
            // La taille suit l'échelle locale : on transforme aussi un point du bord de la prise
            [$bx, $by] = $modele->transformer($p->getX() + $p->getRayon(), $p->getY());
            $rayon = hypot($bx - $x, ($by - $y) * $rapport);

            $prises[] = (new Prise())
                ->setX($x)
                ->setY($y)
                ->setRayon($rayon > 0 ? $rayon : $p->getRayon())
                ->setType($p->getType())
                ->setOrigine($p->getId());
        }

        return $prises;
    }

    /**
     * Prises à charger dans l'éditeur selon la photo choisie : celles de la voie sur sa
     * photo actuelle, et celles replacées sur chaque nouvelle photo proposée.
     *
     * @return array<int, list<array<string, mixed>>> identifiant de la photo → prises (format de l'éditeur)
     */
    public function prisesParPhoto(Voie $voie): array
    {
        $enJson = static fn (iterable $prises): array => array_map(
            static fn (Prise $p) => $p->toArray() + ['o' => $p->getOrigine() ?? $p->getId()],
            [...$prises],
        );

        $parPhoto = [$voie->getPhoto()->getId() => $enJson($voie->getPrises())];
        foreach ($this->recalages->depuis([$voie->getPhoto()]) as $r) {
            $parPhoto[$r->getPhotoCible()->getId()] ??= $enJson($this->prisesProposees($voie, $r));
        }

        return $parPhoto;
    }

    public function ignorer(Voie $voie, Recalage $recalage): void
    {
        $recalage->ignorer($voie);
        $this->em->flush();
    }
}
