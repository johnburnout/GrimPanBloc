<?php

namespace App\Sauvegarde;

use App\Enum\TypeSauvegarde;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Dossier des sauvegardes (var/sauvegardes/, hors du web et conservé par les mises
 * à jour). La date et le type sont dans le nom du fichier :
 * sauvegarde-AAAAMMJJ-HHMMSS-<type>[-N].zip, pour lister sans ouvrir les archives.
 */
final class DepotSauvegardes
{
    private const MOTIF_NOM = '/^sauvegarde-(\d{8}-\d{6})-(manuelle|automatique|importee)(?:-(\d+))?\.zip$/';

    private readonly Filesystem $fs;

    public function __construct(#[Autowire('%sauvegardes_dir%')] private readonly string $dossier)
    {
        $this->fs = new Filesystem();
    }

    public function dossier(): string
    {
        return $this->dossier;
    }

    /** @return list<Sauvegarde> de la plus récente à la plus ancienne */
    public function lister(): array
    {
        $liste = [];
        foreach (is_dir($this->dossier) ? (scandir($this->dossier) ?: []) : [] as $nom) {
            if ($s = $this->lire($nom)) {
                $liste[] = $s;
            }
        }
        // Même seconde : « …-2.zip » est plus récente que « ….zip »
        $rang = fn (Sauvegarde $s): int => preg_match(self::MOTIF_NOM, $s->nom, $m) && isset($m[3]) ? (int) $m[3] : 1;
        usort($liste, fn (Sauvegarde $a, Sauvegarde $b) => [$b->creeLe, $rang($b)] <=> [$a->creeLe, $rang($a)]);

        return $liste;
    }

    /** Sauvegarde de ce nom, ou null (nom invalide ou fichier absent). */
    public function trouver(string $nom): ?Sauvegarde
    {
        return $this->lire($nom);
    }

    /** Date de la dernière sauvegarde faite sur ce site (les archives importées ne comptent pas). */
    public function derniereDate(): ?\DateTimeImmutable
    {
        foreach ($this->lister() as $s) {
            if (TypeSauvegarde::Importee !== $s->type) {
                return $s->creeLe;
            }
        }

        return null;
    }

    /** Chemin libre pour une nouvelle sauvegarde, dossier créé au besoin. */
    public function nouveauChemin(TypeSauvegarde $type): string
    {
        $this->fs->mkdir($this->dossier);
        $base = $this->dossier.'/sauvegarde-'.date('Ymd-His').'-'.$type->value;
        // Même seconde : numéro supérieur à tous ceux déjà pris (même si une purge a libéré
        // un nom plus petit), pour que l'ordre des noms reste celui de création
        $dernier = 0;
        foreach (glob($base.'*.zip') ?: [] as $existant) {
            if (preg_match(self::MOTIF_NOM, basename($existant), $m)) {
                $dernier = max($dernier, isset($m[3]) ? (int) $m[3] : 1);
            }
        }

        return 0 === $dernier ? $base.'.zip' : $base.'-'.($dernier + 1).'.zip';
    }

    public function supprimer(Sauvegarde $s): void
    {
        $this->fs->remove($s->chemin);
    }

    /**
     * Ne garde que les $garder sauvegardes automatiques les plus récentes.
     *
     * @return list<Sauvegarde> les sauvegardes supprimées
     */
    public function purgerAutomatiques(int $garder): array
    {
        $automatiques = array_values(array_filter($this->lister(), fn (Sauvegarde $s) => TypeSauvegarde::Automatique === $s->type));
        $supprimees = \array_slice($automatiques, $garder);
        foreach ($supprimees as $s) {
            $this->supprimer($s);
        }

        return $supprimees;
    }

    public function tailleTotale(): int
    {
        return array_sum(array_map(fn (Sauvegarde $s) => $s->taille, $this->lister()));
    }

    private function lire(string $nom): ?Sauvegarde
    {
        if (!preg_match(self::MOTIF_NOM, $nom, $m) || !is_file($chemin = $this->dossier.'/'.$nom)) {
            return null;
        }
        $creeLe = \DateTimeImmutable::createFromFormat('!Ymd-His', $m[1]);
        if (false === $creeLe) {
            return null;
        }

        return new Sauvegarde($nom, $chemin, TypeSauvegarde::from($m[2]), $creeLe, (int) filesize($chemin));
    }
}
