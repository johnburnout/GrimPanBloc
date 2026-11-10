<?php

namespace App\Sauvegarde;

use App\Enum\SystemeCotation;
use App\Enum\TypePrise;
use App\Enum\TypeSauvegarde;
use App\MiseAJour\VersionInstallee;
use App\Service\MiniaturesPhotos;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Écriture et lecture d'une archive de sauvegarde (zip) :
 *
 *   resume.json      date, version, motif, secteurs avec leurs nombres de voies et photos ;
 *   donnees.json     secteurs, photos, voies (avec prises et croix) ;
 *   photos/…         les photos, telles qu'enregistrées dans uploads/photos/ ;
 *   miniatures/…     leurs miniatures.
 *
 * Les identifiants sont conservés (liens et QR codes vers les voies restent valables
 * après une restauration). Les auteurs et grimpeurs sont désignés par leur pseudo :
 * les comptes utilisateurs ne font pas partie de la sauvegarde.
 *
 * Une archive peut venir d'ailleurs (import) : lire() contrôle tout ce qu'elle contient.
 *
 * @phpstan-type SecteurArchive array{id: int, nom: string, description: ?string}
 * @phpstan-type PhotoArchive array{id: int, secteur: int, fichier: string, largeur: int, hauteur: int, auteur: string, creeLe: string}
 * @phpstan-type PriseArchive array{x: float, y: float, rayon: float, type: string}
 * @phpstan-type CroixArchive array{grimpeur: string, cotation: ?string, creeLe: string}
 * @phpstan-type VoieArchive array{id: int, photo: int, nom: string, description: ?string, auteur: string, systeme: string, cotation: string, cotationAuteur: string, rang: int, active: bool, creeLe: string, prises: list<PriseArchive>, croix: list<CroixArchive>}
 * @phpstan-type DonneesArchive array{secteurs: list<SecteurArchive>, photos: list<PhotoArchive>, voies: list<VoieArchive>}
 */
final class ArchiveSauvegarde
{
    public const FORMAT = 1;
    /** Nom des photos enregistrées par l'application (PhotoUploader) : rien d'autre n'est extrait. */
    public const MOTIF_FICHIER = '/^[A-Za-z0-9_-]{1,100}\.(jpe?g|png|webp)$/i';
    private const DONNEES_MAX = 64 * 1024 * 1024;
    private const DATE = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';

    public function __construct(
        private readonly Connection $connexion,
        private readonly VersionInstallee $version,
        #[Autowire('%photos_dir%')] private readonly string $photosDir,
    ) {
    }

    /**
     * Sauvegarde secteurs, voies et photos dans $chemin (écrit sous un nom temporaire,
     * puis renommé : une archive interrompue n'apparaît jamais dans la liste).
     */
    public function ecrire(string $chemin, TypeSauvegarde $type, string $motif): ResumeSauvegarde
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('L\'extension PHP « zip » est absente : sauvegarde impossible.');
        }
        $donnees = $this->exporter();

        $fichiers = [];
        $sansFichier = 0;
        $taille = 0;
        foreach ($donnees['photos'] as $p) {
            $photo = $this->photosDir.'/'.$p['fichier'];
            if (!is_file($photo)) {
                ++$sansFichier;
                continue;
            }
            $fichiers['photos/'.$p['fichier']] = $photo;
            $taille += (int) filesize($photo);
            if (is_file($miniature = $this->photosDir.'/'.MiniaturesPhotos::DOSSIER.'/'.$p['fichier'])) {
                $fichiers['miniatures/'.$p['fichier']] = $miniature;
                $taille += (int) filesize($miniature);
            }
        }
        $this->verifierEspaceLibre(\dirname($chemin), $taille);

        $resume = new ResumeSauvegarde(self::FORMAT, $this->version->get(), $type, $motif, new \DateTimeImmutable(), $this->compter($donnees), $sansFichier);

        $temporaire = \dirname($chemin).'/.en-cours-'.bin2hex(random_bytes(6)).'.zip';
        $zip = new \ZipArchive();
        if (true !== $zip->open($temporaire, \ZipArchive::CREATE | \ZipArchive::EXCL)) {
            throw new \RuntimeException('Impossible de créer l\'archive (dossier des sauvegardes non accessible en écriture ?).');
        }
        try {
            $zip->setArchiveComment('GrimPanBloc : sauvegarde des secteurs, voies et photos');
            $zip->addFromString('resume.json', $this->json($resume->versTableau()));
            $zip->addFromString('donnees.json', $this->json(['format' => self::FORMAT] + $donnees));
            foreach ($fichiers as $nom => $source) {
                $zip->addFile($source, $nom);
                // Déjà compressées (JPEG, WebP…) : les recompresser ne ferait que coûter du temps
                $zip->setCompressionName($nom, \ZipArchive::CM_STORE);
            }
            if (!$zip->close()) {
                throw new \RuntimeException('Écriture de l\'archive impossible : '.$zip->getStatusString());
            }
            if (!@rename($temporaire, $chemin)) {
                throw new \RuntimeException('Impossible d\'enregistrer l\'archive dans le dossier des sauvegardes.');
            }
        } catch (\Throwable $e) {
            @unlink($temporaire);
            throw $e;
        }

        return $resume;
    }

    /** @throws \InvalidArgumentException si ce n'est pas une sauvegarde GrimPanBloc lisible */
    public function lireResume(string $chemin): ResumeSauvegarde
    {
        $zip = $this->ouvrir($chemin);
        try {
            $resume = ResumeSauvegarde::depuisTableau(json_decode((string) $zip->getFromName('resume.json'), true));
        } finally {
            $zip->close();
        }
        if ($resume->format > self::FORMAT) {
            throw new \InvalidArgumentException('Cette sauvegarde vient d\'une version plus récente de GrimPanBloc : mettez d\'abord le site à jour.');
        }

        return $resume;
    }

    /**
     * Données de l'archive, entièrement contrôlées (types, longueurs, références).
     *
     * @return DonneesArchive
     *
     * @throws \InvalidArgumentException
     */
    public function lireDonnees(string $chemin): array
    {
        $zip = $this->ouvrir($chemin);
        try {
            $stat = $zip->statName('donnees.json');
            if (false === $stat || $stat['size'] > self::DONNEES_MAX) {
                throw new \InvalidArgumentException('Archive invalide : données absentes ou trop volumineuses.');
            }
            $d = json_decode((string) $zip->getFromName('donnees.json'), true);
        } finally {
            $zip->close();
        }

        return $this->valider($d);
    }

    /** Ouvre l'archive en lecture (pour en extraire les photos). */
    public function ouvrir(string $chemin): \ZipArchive
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('L\'extension PHP « zip » est absente.');
        }
        $zip = new \ZipArchive();
        if (true !== $zip->open($chemin, \ZipArchive::RDONLY) || false === $zip->locateName('resume.json')) {
            throw new \InvalidArgumentException('Ce fichier n\'est pas une sauvegarde GrimPanBloc.');
        }

        return $zip;
    }

    /**
     * Nombre de voies et de photos par secteur.
     *
     * @param DonneesArchive $donnees
     *
     * @return list<array{id: int, nom: string, voies: int, photos: int}>
     */
    public function compter(array $donnees): array
    {
        $secteurDePhoto = array_column($donnees['photos'], 'secteur', 'id');
        $photos = array_count_values(array_column($donnees['photos'], 'secteur'));
        $voies = [];
        foreach ($donnees['voies'] as $v) {
            $s = $secteurDePhoto[$v['photo']];
            $voies[$s] = ($voies[$s] ?? 0) + 1;
        }

        return array_map(fn (array $s) => [
            'id' => $s['id'], 'nom' => $s['nom'], 'voies' => $voies[$s['id']] ?? 0, 'photos' => $photos[$s['id']] ?? 0,
        ], $donnees['secteurs']);
    }

    // ---------------------------------------------------------------------------

    /** @return DonneesArchive */
    private function exporter(): array
    {
        $c = $this->connexion;
        // Lectures dans une même transaction : un instantané cohérent de la base
        $c->beginTransaction();
        try {
            $secteurs = $c->fetchAllAssociative('SELECT id, nom, description FROM secteur ORDER BY id');
            $photos = $c->fetchAllAssociative('SELECT p.id, p.secteur_id, p.fichier, p.largeur, p.hauteur, p.cree_le, u.pseudo FROM photo p JOIN utilisateur u ON u.id = p.auteur_id ORDER BY p.id');
            $voies = $c->fetchAllAssociative('SELECT v.id, v.photo_id, v.nom, v.description, v.systeme, v.cotation, v.cotation_auteur, v.rang, v.active, v.cree_le, u.pseudo FROM voie v JOIN utilisateur u ON u.id = v.auteur_id ORDER BY v.id');
            $prises = $c->fetchAllAssociative('SELECT voie_id, x, y, rayon, type FROM prise ORDER BY id');
            $croix = $c->fetchAllAssociative('SELECT c.voie_id, c.cotation, c.cree_le, u.pseudo FROM croix c JOIN utilisateur u ON u.id = c.grimpeur_id ORDER BY c.id');
            $c->commit();
        } catch (\Throwable $e) {
            $c->rollBack();
            throw $e;
        }

        $prisesParVoie = [];
        foreach ($prises as $p) {
            $prisesParVoie[(int) $p['voie_id']][] = ['x' => (float) $p['x'], 'y' => (float) $p['y'], 'rayon' => (float) $p['rayon'], 'type' => (string) $p['type']];
        }
        $croixParVoie = [];
        foreach ($croix as $x) {
            $croixParVoie[(int) $x['voie_id']][] = ['grimpeur' => (string) $x['pseudo'], 'cotation' => null === $x['cotation'] ? null : (string) $x['cotation'], 'creeLe' => (string) $x['cree_le']];
        }

        return [
            'secteurs' => array_map(fn (array $s) => [
                'id' => (int) $s['id'], 'nom' => (string) $s['nom'], 'description' => null === $s['description'] ? null : (string) $s['description'],
            ], $secteurs),
            'photos' => array_map(fn (array $p) => [
                'id' => (int) $p['id'], 'secteur' => (int) $p['secteur_id'], 'fichier' => (string) $p['fichier'],
                'largeur' => (int) $p['largeur'], 'hauteur' => (int) $p['hauteur'], 'auteur' => (string) $p['pseudo'], 'creeLe' => (string) $p['cree_le'],
            ], $photos),
            'voies' => array_map(fn (array $v) => [
                'id' => (int) $v['id'], 'photo' => (int) $v['photo_id'], 'nom' => (string) $v['nom'],
                'description' => null === $v['description'] ? null : (string) $v['description'], 'auteur' => (string) $v['pseudo'],
                'systeme' => (string) $v['systeme'], 'cotation' => (string) $v['cotation'], 'cotationAuteur' => (string) $v['cotation_auteur'],
                'rang' => (int) $v['rang'], 'active' => (bool) $v['active'], 'creeLe' => (string) $v['cree_le'],
                'prises' => $prisesParVoie[(int) $v['id']] ?? [], 'croix' => $croixParVoie[(int) $v['id']] ?? [],
            ], $voies),
        ];
    }

    /**
     * @return DonneesArchive
     *
     * @throws \InvalidArgumentException
     */
    private function valider(mixed $d): array
    {
        if (!\is_array($d) || !\is_int($d['format'] ?? null)) {
            throw new \InvalidArgumentException('Archive invalide : données illisibles.');
        }
        if ($d['format'] > self::FORMAT) {
            throw new \InvalidArgumentException('Cette sauvegarde vient d\'une version plus récente de GrimPanBloc : mettez d\'abord le site à jour.');
        }

        $secteurs = [];
        foreach ($this->liste($d, 'secteurs') as $s) {
            $id = $this->id($s, 'id');
            if (isset($secteurs[$id])) {
                throw $this->invalide("secteur $id en double");
            }
            $secteurs[$id] = ['id' => $id, 'nom' => $this->texte($s, 'nom', 100), 'description' => $this->texteOuNull($s, 'description', 65535)];
        }

        $photos = [];
        $fichiers = [];
        foreach ($this->liste($d, 'photos') as $p) {
            $id = $this->id($p, 'id');
            $fichier = $this->texte($p, 'fichier', 255);
            if (isset($photos[$id]) || isset($fichiers[$fichier]) || !preg_match(self::MOTIF_FICHIER, $fichier)) {
                throw $this->invalide("photo $id");
            }
            $fichiers[$fichier] = true;
            $secteur = $this->id($p, 'secteur');
            if (!isset($secteurs[$secteur])) {
                throw $this->invalide("la photo $id appartient à un secteur absent");
            }
            $photos[$id] = [
                'id' => $id, 'secteur' => $secteur, 'fichier' => $fichier,
                'largeur' => $this->entier($p, 'largeur', 0, 100000), 'hauteur' => $this->entier($p, 'hauteur', 0, 100000),
                'auteur' => $this->texte($p, 'auteur', 50), 'creeLe' => $this->date($p, 'creeLe'),
            ];
        }

        $voies = [];
        foreach ($this->liste($d, 'voies') as $v) {
            $id = $this->id($v, 'id');
            $photo = $this->id($v, 'photo');
            if (isset($voies[$id]) || !isset($photos[$photo])) {
                throw $this->invalide("voie $id");
            }
            $systeme = $this->texte($v, 'systeme', 10);
            if (null === SystemeCotation::tryFrom($systeme)) {
                throw $this->invalide("cotation de la voie $id");
            }
            $prises = [];
            foreach ($this->liste($v, 'prises') as $p) {
                $type = $this->texte($p, 'type', 10);
                if (null === TypePrise::tryFrom($type)) {
                    throw $this->invalide("prise de la voie $id");
                }
                $prises[] = ['x' => $this->decimal($p, 'x'), 'y' => $this->decimal($p, 'y'), 'rayon' => $this->decimal($p, 'rayon'), 'type' => $type];
            }
            $croix = [];
            foreach ($this->liste($v, 'croix') as $c) {
                $croix[] = ['grimpeur' => $this->texte($c, 'grimpeur', 50), 'cotation' => $this->texteOuNull($c, 'cotation', 10), 'creeLe' => $this->date($c, 'creeLe')];
            }
            if (!\is_bool($v['active'] ?? null)) {
                throw $this->invalide("voie $id");
            }
            $voies[$id] = [
                'id' => $id, 'photo' => $photo, 'nom' => $this->texte($v, 'nom', 100), 'description' => $this->texteOuNull($v, 'description', 65535),
                'auteur' => $this->texte($v, 'auteur', 50), 'systeme' => $systeme,
                'cotation' => $this->texte($v, 'cotation', 10, true), 'cotationAuteur' => $this->texte($v, 'cotationAuteur', 10, true),
                'rang' => $this->entier($v, 'rang', -1000, 1000), 'active' => $v['active'], 'creeLe' => $this->date($v, 'creeLe'),
                'prises' => $prises, 'croix' => $croix,
            ];
        }

        return ['secteurs' => array_values($secteurs), 'photos' => array_values($photos), 'voies' => array_values($voies)];
    }

    /**
     * @param array<mixed> $d
     *
     * @return list<array<mixed>>
     */
    private function liste(array $d, string $cle): array
    {
        if (!\is_array($d[$cle] ?? null) || !array_is_list($d[$cle])) {
            throw $this->invalide($cle);
        }
        foreach ($d[$cle] as $element) {
            if (!\is_array($element)) {
                throw $this->invalide($cle);
            }
        }

        /* @var list<array<mixed>> */
        return $d[$cle];
    }

    /** @param array<mixed> $d */
    private function id(array $d, string $cle): int
    {
        return $this->entier($d, $cle, 1, \PHP_INT_MAX);
    }

    /** @param array<mixed> $d */
    private function entier(array $d, string $cle, int $min, int $max): int
    {
        $v = $d[$cle] ?? null;
        if (!\is_int($v) || $v < $min || $v > $max) {
            throw $this->invalide($cle);
        }

        return $v;
    }

    /** @param array<mixed> $d */
    private function decimal(array $d, string $cle): float
    {
        $v = $d[$cle] ?? null;
        if ((!\is_int($v) && !\is_float($v)) || $v < 0 || $v > 100) {
            throw $this->invalide($cle);
        }

        return (float) $v;
    }

    /** @param array<mixed> $d */
    private function texte(array $d, string $cle, int $longueurMax, bool $videPermis = false): string
    {
        $v = $d[$cle] ?? null;
        if (!\is_string($v) || mb_strlen($v) > $longueurMax || (!$videPermis && '' === trim($v))) {
            throw $this->invalide($cle);
        }

        return $v;
    }

    /** @param array<mixed> $d */
    private function texteOuNull(array $d, string $cle, int $longueurMax): ?string
    {
        return null === ($d[$cle] ?? null) ? null : $this->texte($d, $cle, $longueurMax, true);
    }

    /** @param array<mixed> $d */
    private function date(array $d, string $cle): string
    {
        $v = $d[$cle] ?? null;
        if (!\is_string($v) || !preg_match(self::DATE, $v)) {
            throw $this->invalide($cle);
        }

        return $v;
    }

    private function invalide(string $detail): \InvalidArgumentException
    {
        return new \InvalidArgumentException("Archive invalide ($detail).");
    }

    /** @param array<mixed> $d */
    private function json(array $d): string
    {
        return json_encode($d, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR);
    }

    private function verifierEspaceLibre(string $dossier, int $besoin): void
    {
        // disk_free_space() est souvent désactivée sur les hébergements mutualisés : contrôle au mieux
        $libre = \function_exists('disk_free_space') ? @disk_free_space($dossier) : false;
        if (false !== $libre && $libre < $besoin * 1.1 + 10 * 1024 * 1024) {
            throw new \RuntimeException(\sprintf('Espace disque insuffisant pour la sauvegarde : %s Mo nécessaires, %s Mo libres. Supprimez d\'anciennes sauvegardes.', number_format($besoin / 1048576, 0, ',', ' '), number_format($libre / 1048576, 0, ',', ' ')));
        }
    }
}
