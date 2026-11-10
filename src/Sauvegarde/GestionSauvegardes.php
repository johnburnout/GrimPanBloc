<?php

namespace App\Sauvegarde;

use App\Entity\Secteur;
use App\Entity\User;
use App\Enum\TypeSauvegarde;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Lock\LockFactory;

/**
 * Sauvegarde et restauration des secteurs, voies et photos (Administration → Sauvegardes).
 *
 * Une seule opération à la fois (verrou) : deux restaurations simultanées, ou une
 * sauvegarde pendant une restauration, donneraient un résultat incohérent.
 *
 * Sauvegardes automatiques : avant chaque restauration, nettoyage de secteur et mise
 * à jour, et quand la dernière date de plus de « sauvegardes_intervalle_jours » jours
 * (voir SauvegardeReguliereListener). Seules les N dernières automatiques sont gardées
 * (ReglageSauvegardes) ; les manuelles et importées ne sont jamais supprimées d'office.
 *
 * @phpstan-import-type DonneesArchive from ArchiveSauvegarde
 */
final class GestionSauvegardes
{
    public function __construct(
        private readonly DepotSauvegardes $depot,
        private readonly ArchiveSauvegarde $archive,
        private readonly Restauration $restauration,
        private readonly ReglageSauvegardes $reglage,
        private readonly LockFactory $verrous,
        private readonly LoggerInterface $logger,
        #[Autowire('%sauvegardes_intervalle_jours%')] private readonly int $intervalleJours,
    ) {
    }

    public function creerManuelle(string $motif = ''): Sauvegarde
    {
        return $this->sousVerrou(fn () => $this->creer(TypeSauvegarde::Manuelle, $motif));
    }

    public function creerAutomatique(string $motif): Sauvegarde
    {
        return $this->sousVerrou(fn () => $this->creer(TypeSauvegarde::Automatique, $motif));
    }

    /**
     * Sauvegarde régulière, si la dernière est trop ancienne. Ne lève jamais d'exception
     * (appelée après la réponse à une requête) ; null si rien n'a été fait.
     */
    public function sauvegardeReguliereSiNecessaire(): ?Sauvegarde
    {
        if ($this->intervalleJours <= 0) {
            return null;
        }
        $derniere = $this->depot->derniereDate();
        if (null !== $derniere && $derniere > new \DateTimeImmutable("-{$this->intervalleJours} days")) {
            return null;
        }
        try {
            return $this->sousVerrou(fn () => $this->creer(TypeSauvegarde::Automatique, 'sauvegarde régulière'));
        } catch (\Throwable $e) {
            $this->logger->error('Sauvegarde régulière impossible : {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return null;
        }
    }

    /**
     * Restaure toute la sauvegarde ($secteur null) ou un seul de ses secteurs, après
     * une sauvegarde automatique de l'état actuel.
     *
     * @return array{avant: Sauvegarde, bilan: BilanRestauration}
     */
    public function restaurer(Sauvegarde $sauvegarde, ?int $secteur, User $admin): array
    {
        return $this->sousVerrou(function () use ($sauvegarde, $secteur, $admin): array {
            $donnees = $this->archive->lireDonnees($sauvegarde->chemin);
            $nom = null;
            if (null !== $secteur) {
                $donnees = $this->filtrerSecteur($donnees, $secteur);
                $nom = $donnees['secteurs'][0]['nom'];
            }
            $date = $sauvegarde->creeLe->format('d/m/Y H:i');
            $avant = $this->creer(TypeSauvegarde::Automatique, null === $nom
                ? "avant la restauration de la sauvegarde du $date"
                : "avant la restauration du secteur « $nom » (sauvegarde du $date)");

            $zip = $this->archive->ouvrir($sauvegarde->chemin);
            try {
                $bilan = $this->restauration->remplacer($donnees, null === $secteur ? null : [$secteur], $zip, (int) $admin->getId());
            } finally {
                $zip->close();
            }

            return ['avant' => $avant, 'bilan' => $bilan];
        });
    }

    /**
     * Supprime toutes les voies et toutes les photos du secteur (le secteur reste),
     * après une sauvegarde automatique.
     *
     * @return array{avant: Sauvegarde, bilan: BilanRestauration}
     */
    public function nettoyerSecteur(Secteur $secteur, User $admin): array
    {
        return $this->sousVerrou(function () use ($secteur, $admin): array {
            $avant = $this->creer(TypeSauvegarde::Automatique, "avant le nettoyage du secteur « {$secteur->getNom()} »");
            $bilan = $this->restauration->remplacer(['secteurs' => [], 'photos' => [], 'voies' => []], [(int) $secteur->getId()], null, (int) $admin->getId());

            return ['avant' => $avant, 'bilan' => $bilan];
        });
    }

    /**
     * Ajoute une archive envoyée (téléchargée auparavant) à la liste des sauvegardes.
     *
     * @throws \InvalidArgumentException si ce n'est pas une sauvegarde valide
     */
    public function importer(UploadedFile $fichier): Sauvegarde
    {
        // Contrôle complet avant de la garder : une archive invalide est refusée tout de suite
        $this->archive->lireResume($fichier->getPathname());
        $this->archive->lireDonnees($fichier->getPathname());

        $chemin = $this->depot->nouveauChemin(TypeSauvegarde::Importee);
        $fichier->move(\dirname($chemin), basename($chemin));

        return $this->depot->trouver(basename($chemin)) ?? throw new \RuntimeException('Archive importée introuvable.');
    }

    public function supprimer(Sauvegarde $sauvegarde): void
    {
        $this->sousVerrou(fn () => $this->depot->supprimer($sauvegarde));
    }

    /**
     * Résumé de chaque sauvegarde (null si l'archive est illisible).
     *
     * @return list<array{sauvegarde: Sauvegarde, resume: ?ResumeSauvegarde, erreur: ?string}>
     */
    public function lister(): array
    {
        return array_map(function (Sauvegarde $s): array {
            try {
                return ['sauvegarde' => $s, 'resume' => $this->archive->lireResume($s->chemin), 'erreur' => null];
            } catch (\Throwable $e) {
                return ['sauvegarde' => $s, 'resume' => null, 'erreur' => $e->getMessage()];
            }
        }, $this->depot->lister());
    }

    // ---------------------------------------------------------------------------

    /** À appeler sous verrou. */
    private function creer(TypeSauvegarde $type, string $motif): Sauvegarde
    {
        $chemin = $this->depot->nouveauChemin($type);
        $this->archive->ecrire($chemin, $type, $motif);
        if (TypeSauvegarde::Automatique === $type) {
            $this->depot->purgerAutomatiques($this->reglage->automatiquesGardees());
        }

        return $this->depot->trouver(basename($chemin)) ?? throw new \RuntimeException('Sauvegarde introuvable après sa création.');
    }

    /**
     * @param DonneesArchive $donnees
     *
     * @return DonneesArchive
     */
    private function filtrerSecteur(array $donnees, int $secteur): array
    {
        $secteurs = array_values(array_filter($donnees['secteurs'], fn (array $s) => $s['id'] === $secteur));
        if ([] === $secteurs) {
            throw new \InvalidArgumentException('Ce secteur ne fait pas partie de la sauvegarde.');
        }
        $photos = array_values(array_filter($donnees['photos'], fn (array $p) => $p['secteur'] === $secteur));
        $idsPhotos = array_flip(array_column($photos, 'id'));
        $voies = array_values(array_filter($donnees['voies'], fn (array $v) => isset($idsPhotos[$v['photo']])));

        return ['secteurs' => $secteurs, 'photos' => $photos, 'voies' => $voies];
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    private function sousVerrou(callable $operation): mixed
    {
        $verrou = $this->verrous->createLock('grimpanbloc-sauvegardes', 3600);
        if (!$verrou->acquire()) {
            throw new \RuntimeException('Une sauvegarde ou une restauration est déjà en cours : réessayez dans quelques minutes.');
        }
        try {
            return $operation();
        } finally {
            $verrou->release();
        }
    }
}
