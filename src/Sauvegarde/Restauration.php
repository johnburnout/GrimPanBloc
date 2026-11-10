<?php

namespace App\Sauvegarde;

use App\Entity\Voie;
use App\Service\MiniaturesPhotos;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Remplace le contenu de secteurs (voies, prises, croix, photos et leurs fichiers)
 * par celui d'une sauvegarde. Avec des données vides, c'est le nettoyage d'un secteur.
 *
 * La base est modifiée dans une transaction ; les fichiers des photos restaurées sont
 * écrits avant (et effacés si la transaction échoue), ceux des photos remplacées sont
 * effacés après (en cas d'échec, mieux vaut un fichier orphelin qu'une photo sans fichier).
 *
 * Auteurs et grimpeurs sont retrouvés par leur pseudo. Une voie ou une photo dont
 * l'auteur n'existe pas (plus) sur ce site est attribuée à l'administrateur qui
 * restaure ; une croix dont le grimpeur n'existe pas est ignorée.
 *
 * @phpstan-import-type DonneesArchive from ArchiveSauvegarde
 */
final class Restauration
{
    private const TAILLE_MAX_PHOTO = 100 * 1024 * 1024;

    private readonly Filesystem $fs;

    public function __construct(
        private readonly Connection $connexion,
        private readonly EntityManagerInterface $em,
        private readonly MiniaturesPhotos $miniatures,
        #[Autowire('%photos_dir%')] private readonly string $photosDir,
    ) {
        $this->fs = new Filesystem();
    }

    /**
     * @param DonneesArchive $donnees ce qui doit remplacer le contenu actuel
     * @param list<int>|null $cibles secteurs dont le contenu est remplacé ; null : tous
     *                               (les secteurs absents de la sauvegarde sont alors supprimés)
     * @param \ZipArchive|null $zip archive d'où extraire les fichiers des photos
     * @param int $remplacant utilisateur à qui attribuer voies et photos sans auteur connu
     */
    public function remplacer(array $donnees, ?array $cibles, ?\ZipArchive $zip, int $remplacant): BilanRestauration
    {
        if (\function_exists('set_time_limit')) {
            @set_time_limit(600);
        }
        [$ecrits, $sansFichier] = $this->ecrireFichiers($donnees, $zip);
        $c = $this->connexion;

        $c->beginTransaction();
        try {
            $anciens = $this->fichiersDesSecteurs($cibles);
            [$voiesSupprimees, $photosSupprimees] = $this->vider($cibles, array_column($donnees['secteurs'], 'id'));

            $utilisateurs = [];
            foreach ($c->fetchAllAssociative('SELECT id, pseudo FROM utilisateur') as $u) {
                $utilisateurs[mb_strtolower((string) $u['pseudo'])] = (int) $u['id'];
            }
            $auteur = fn (string $pseudo): int => $utilisateurs[mb_strtolower($pseudo)] ?? $remplacant;

            foreach ($donnees['secteurs'] as $s) {
                $existe = false !== $c->fetchOne('SELECT 1 FROM secteur WHERE id = ?', [$s['id']]);
                $existe
                    ? $c->update('secteur', ['nom' => $s['nom'], 'description' => $s['description']], ['id' => $s['id']])
                    : $c->insert('secteur', ['id' => $s['id'], 'nom' => $s['nom'], 'description' => $s['description']]);
            }

            $idsPhotos = [];
            foreach ($donnees['photos'] as $p) {
                $idsPhotos[$p['id']] = $this->inserer('photo', $p['id'], [
                    'secteur_id' => $p['secteur'], 'fichier' => $p['fichier'], 'largeur' => $p['largeur'], 'hauteur' => $p['hauteur'],
                    'auteur_id' => $auteur($p['auteur']), 'cree_le' => $p['creeLe'],
                ]);
            }

            $idsVoies = [];
            $croix = 0;
            $croixIgnorees = 0;
            foreach ($donnees['voies'] as $v) {
                $id = $idsVoies[] = $this->inserer('voie', $v['id'], [
                    'photo_id' => $idsPhotos[$v['photo']], 'nom' => $v['nom'], 'description' => $v['description'],
                    'auteur_id' => $auteur($v['auteur']), 'systeme' => $v['systeme'], 'cotation' => $v['cotation'],
                    'cotation_auteur' => $v['cotationAuteur'], 'rang' => $v['rang'], 'active' => (int) $v['active'], 'cree_le' => $v['creeLe'],
                ]);
                foreach ($v['prises'] as $p) {
                    $c->insert('prise', ['voie_id' => $id, 'x' => $p['x'], 'y' => $p['y'], 'rayon' => $p['rayon'], 'type' => $p['type']]);
                }
                $grimpeurs = [];
                foreach ($v['croix'] as $x) {
                    $grimpeur = $utilisateurs[mb_strtolower($x['grimpeur'])] ?? null;
                    if (null === $grimpeur || isset($grimpeurs[$grimpeur])) {
                        ++$croixIgnorees;
                        continue;
                    }
                    $grimpeurs[$grimpeur] = true;
                    $c->insert('croix', ['voie_id' => $id, 'grimpeur_id' => $grimpeur, 'cotation' => $x['cotation'], 'cree_le' => $x['creeLe']]);
                    ++$croix;
                }
            }

            $this->em->clear();
            if ($croixIgnorees > 0) {
                // Sans certaines croix, la cotation consensuelle peut changer
                foreach ($this->em->getRepository(Voie::class)->findBy(['id' => $idsVoies]) as $voie) {
                    $voie->recalculerCotation();
                }
                $this->em->flush();
            }
            $c->commit();
        } catch (\Throwable $e) {
            $c->rollBack();
            $this->em->clear();
            $this->fs->remove($ecrits);
            throw $e;
        }

        // Fichiers des photos remplacées qui ne servent plus
        $gardes = array_flip(array_column($donnees['photos'], 'fichier'));
        foreach ($anciens as $fichier) {
            if (!isset($gardes[$fichier]) && preg_match(ArchiveSauvegarde::MOTIF_FICHIER, $fichier)) {
                $this->fs->remove($this->photosDir.'/'.$fichier);
                $this->miniatures->supprimer($fichier);
            }
        }
        // Miniatures absentes de l'archive : au mieux (sans miniature, la photo s'affiche en entier)
        foreach ($donnees['photos'] as $p) {
            if (is_file($this->photosDir.'/'.$p['fichier']) && !$this->miniatures->existe($p['fichier'])) {
                try {
                    $this->miniatures->creer($p['fichier']);
                } catch (\Throwable) {
                }
            }
        }

        return new BilanRestauration(
            secteurs: \count($donnees['secteurs']),
            photos: \count($donnees['photos']),
            voies: \count($donnees['voies']),
            croix: $croix,
            croixIgnorees: $croixIgnorees,
            photosSansFichier: $sansFichier,
            voiesSupprimees: $voiesSupprimees,
            photosSupprimees: $photosSupprimees,
        );
    }

    /**
     * Extrait de l'archive les photos (et miniatures) absentes du dossier des photos.
     *
     * @param DonneesArchive $donnees
     *
     * @return array{list<string>, int} fichiers écrits, photos dont le fichier manque
     */
    private function ecrireFichiers(array $donnees, ?\ZipArchive $zip): array
    {
        $sansFichier = 0;
        $ecrits = [];
        try {
            foreach ($donnees['photos'] as $p) {
                $cible = $this->photosDir.'/'.$p['fichier'];
                if (is_file($cible)) {
                    continue;
                }
                if (!$zip || !$this->extraire($zip, 'photos/'.$p['fichier'], $cible, true)) {
                    ++$sansFichier;
                    continue;
                }
                $ecrits[] = $cible;
                $miniature = $this->photosDir.'/'.MiniaturesPhotos::DOSSIER.'/'.$p['fichier'];
                if (!is_file($miniature) && $this->extraire($zip, 'miniatures/'.$p['fichier'], $miniature, false)) {
                    $ecrits[] = $miniature;
                }
            }
        } catch (\Throwable $e) {
            $this->fs->remove($ecrits);
            throw $e;
        }

        return [$ecrits, $sansFichier];
    }

    /**
     * Copie une entrée de l'archive, après avoir vérifié que c'est bien une image.
     *
     * @return bool false si l'entrée est absente (ou, pour une miniature, invalide)
     */
    private function extraire(\ZipArchive $zip, string $entree, string $cible, bool $obligatoire): bool
    {
        $stat = $zip->statName($entree);
        if (false === $stat) {
            return false;
        }
        $flux = $stat['size'] <= self::TAILLE_MAX_PHOTO ? $zip->getStream($entree) : false;
        if (false === $flux) {
            if ($obligatoire) {
                throw new \InvalidArgumentException("Archive invalide : photo $entree illisible ou trop volumineuse.");
            }

            return false;
        }
        $this->fs->mkdir(\dirname($cible));
        $temporaire = $cible.'.restauration';
        $sortie = @fopen($temporaire, 'x');
        if (false === $sortie) {
            fclose($flux);
            throw new \RuntimeException('Impossible d\'écrire dans le dossier des photos.');
        }
        $copie = stream_copy_to_stream($flux, $sortie, self::TAILLE_MAX_PHOTO + 1);
        fclose($flux);
        fclose($sortie);

        // Seules des images JPEG, PNG ou WebP entrent dans uploads/photos/
        $image = false !== $copie && $copie <= self::TAILLE_MAX_PHOTO ? @getimagesize($temporaire) : false;
        if (false === $image || !\in_array($image[2], [\IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_WEBP], true)) {
            @unlink($temporaire);
            if ($obligatoire) {
                throw new \InvalidArgumentException("Archive invalide : $entree n'est pas une image JPEG, PNG ou WebP.");
            }

            return false;
        }
        $this->fs->rename($temporaire, $cible, true);

        return true;
    }

    /**
     * @param list<int>|null $cibles
     *
     * @return list<string>
     */
    private function fichiersDesSecteurs(?array $cibles): array
    {
        return null === $cibles
            ? $this->connexion->fetchFirstColumn('SELECT fichier FROM photo')
            : $this->connexion->fetchFirstColumn('SELECT fichier FROM photo WHERE secteur_id IN (?)', [$cibles], [ArrayParameterType::INTEGER]);
    }

    /**
     * Supprime voies et photos des secteurs ciblés (prises et croix suivent, ON DELETE CASCADE).
     *
     * @param list<int>|null $cibles
     * @param list<int> $secteursGardes en restauration complète, les autres secteurs sont supprimés
     *
     * @return array{int, int} voies et photos supprimées
     */
    private function vider(?array $cibles, array $secteursGardes): array
    {
        $c = $this->connexion;
        if (null === $cibles) {
            $voies = (int) $c->executeStatement('DELETE FROM voie');
            $photos = (int) $c->executeStatement('DELETE FROM photo');
            [] === $secteursGardes
                ? $c->executeStatement('DELETE FROM secteur')
                : $c->executeStatement('DELETE FROM secteur WHERE id NOT IN (?)', [$secteursGardes], [ArrayParameterType::INTEGER]);

            return [$voies, $photos];
        }
        if ([] === $cibles) {
            return [0, 0];
        }
        $voies = (int) $c->executeStatement('DELETE FROM voie WHERE photo_id IN (SELECT id FROM photo WHERE secteur_id IN (?))', [$cibles], [ArrayParameterType::INTEGER]);
        $photos = (int) $c->executeStatement('DELETE FROM photo WHERE secteur_id IN (?)', [$cibles], [ArrayParameterType::INTEGER]);

        return [$voies, $photos];
    }

    /**
     * Insère la ligne en gardant son identifiant d'origine s'il est libre.
     *
     * @param array<string, mixed> $valeurs
     */
    private function inserer(string $table, int $id, array $valeurs): int
    {
        $libre = false === $this->connexion->fetchOne("SELECT 1 FROM $table WHERE id = ?", [$id]);
        $this->connexion->insert($table, ($libre ? ['id' => $id] : []) + $valeurs);

        return $libre ? $id : (int) $this->connexion->lastInsertId();
    }
}
