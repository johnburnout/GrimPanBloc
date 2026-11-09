<?php

namespace App\Site;

use App\Repository\ReglageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Nom et logo de la salle ou du club, affichés dans l'en-tête et sur la fiche à imprimer.
 *
 * Le logo est réenregistré par GD (PNG, JPEG ou WebP, 600 px au plus), ce qui
 * élimine tout contenu indésirable du fichier envoyé. Le SVG est refusé : servi
 * depuis le site, il pourrait contenir du script.
 *
 * Affichés sur chaque page : le nom et le chemin du logo sont gardés dans le cache
 * de l'application, effacé à chaque modification (et à chaque mise à jour du site).
 */
final class IdentiteClub
{
    public const COTE_MAX_LOGO = 600;
    private const TYPES = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
    private const CLE_CACHE = 'identite_club';

    public function __construct(
        private readonly ReglageRepository $reglages,
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
        private readonly string $racine,
    ) {
    }

    public function nom(): string
    {
        return $this->lire()['nom'];
    }

    /** Chemin public du logo (« uploads/logo/club-….png »), ou null. */
    public function logo(): ?string
    {
        $chemin = $this->lire()['logo'];

        return '' !== $chemin && is_file($this->racine.'/public/'.$chemin) ? $chemin : null;
    }

    public function definirNom(string $nom): void
    {
        $this->reglages->definir('club.nom', mb_substr(trim($nom), 0, 100));
        $this->enregistrer();
    }

    public function definirLogo(UploadedFile $fichier): void
    {
        $infos = @getimagesize($fichier->getPathname());
        if (false === $infos || !isset(self::TYPES[$infos[2]])) {
            throw new \InvalidArgumentException('Le logo doit être une image PNG, JPEG ou WebP.');
        }
        [$l, $h, $type] = $infos;
        $source = match ($type) {
            IMAGETYPE_PNG => @imagecreatefrompng($fichier->getPathname()),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($fichier->getPathname()),
            IMAGETYPE_WEBP => @imagecreatefromwebp($fichier->getPathname()),
        };
        if (false === $source) {
            throw new \InvalidArgumentException('Image illisible.');
        }

        $ratio = min(1, self::COTE_MAX_LOGO / max($l, $h));
        $nl = max(1, (int) round($l * $ratio));
        $nh = max(1, (int) round($h * $ratio));
        $image = imagecreatetruecolor($nl, $nh);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $nl, $nh, $l, $h);

        $dossier = $this->racine.'/public/uploads/logo';
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true)) {
            throw new \RuntimeException('Impossible de créer le dossier public/uploads/logo/.');
        }
        $extension = self::TYPES[$type];
        $relatif = 'uploads/logo/club-'.bin2hex(random_bytes(6)).'.'.$extension;
        $ok = match ($extension) {
            'png' => imagepng($image, $this->racine.'/public/'.$relatif, 9),
            'jpg' => imagejpeg($image, $this->racine.'/public/'.$relatif, 90),
            'webp' => imagewebp($image, $this->racine.'/public/'.$relatif, 90),
        };
        if (!$ok) {
            throw new \RuntimeException('Impossible d\'enregistrer le logo.');
        }

        $this->supprimerFichierLogo();
        $this->reglages->definir('club.logo', $relatif);
        $this->enregistrer();
    }

    public function supprimerLogo(): void
    {
        $this->supprimerFichierLogo();
        $this->reglages->definir('club.logo', '');
        $this->enregistrer();
    }

    /** @return array{nom: string, logo: string} valeurs enregistrées (logo : chemin, ou '') */
    private function lire(): array
    {
        return $this->cache->get(self::CLE_CACHE, fn (): array => [
            'nom' => (string) $this->reglages->valeur('club.nom', ''),
            'logo' => (string) $this->reglages->valeur('club.logo', ''),
        ]);
    }

    private function enregistrer(): void
    {
        $this->em->flush();
        $this->cache->delete(self::CLE_CACHE);
    }

    private function supprimerFichierLogo(): void
    {
        if ($ancien = $this->logo()) {
            @unlink($this->racine.'/public/'.$ancien);
        }
    }
}
