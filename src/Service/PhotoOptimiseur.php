<?php

namespace App\Service;

/**
 * Prépare une photo pour le stockage :
 * - la redresse selon l'orientation EXIF (photos de téléphone) ;
 * - la réduit pour qu'elle tienne dans COTE_MAX × COTE_MAX, proportions conservées.
 *
 * Le fichier n'est réenregistré que s'il a changé, pour ne pas perdre de
 * qualité sur une photo déjà à la bonne taille.
 */
class PhotoOptimiseur
{
    public const COTE_MAX = 2000;
    /**
     * Taille maximale acceptée à l'envoi (contrainte Image des formulaires). GD décode
     * l'image entière en mémoire, ~4 octets par pixel, et le double quand une photo
     * est redressée : 25 Mpx ≈ 200 Mo au pire, dans les 256 Mo conseillés à
     * l'installation. Couvre les photos de téléphone (12 Mpx) et la plupart des
     * appareils photo (24 Mpx), sans laisser passer une « bombe de décompression ».
     */
    public const PIXELS_MAX = 25_000_000;
    private const QUALITE_JPEG = 85;
    private const QUALITE_WEBP = 85;

    /**
     * Traite le fichier sur place.
     *
     * @return array{largeur: int, hauteur: int, modifiee: bool}
     */
    public function optimiser(string $chemin): array
    {
        $infos = @getimagesize($chemin);
        if (false === $infos) {
            return ['largeur' => 0, 'hauteur' => 0, 'modifiee' => false];
        }
        [$largeur, $hauteur, $type] = $infos;

        $angle = IMAGETYPE_JPEG === $type ? $this->angleExif($chemin) : 0;
        $tourne = 90 === abs($angle);
        // Dimensions une fois redressée
        [$l, $h] = $tourne ? [$hauteur, $largeur] : [$largeur, $hauteur];
        $ratio = min(1, self::COTE_MAX / max($l, $h));

        if (0 === $angle && $ratio >= 1) {
            return ['largeur' => $largeur, 'hauteur' => $hauteur, 'modifiee' => false];
        }

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($chemin),
            IMAGETYPE_PNG => @imagecreatefrompng($chemin),
            IMAGETYPE_WEBP => @imagecreatefromwebp($chemin),
            default => false,
        };
        if (false === $image) {
            return ['largeur' => $largeur, 'hauteur' => $hauteur, 'modifiee' => false];
        }

        if (0 !== $angle) {
            $image = imagerotate($image, $angle, 0);
        }

        if ($ratio < 1) {
            $nl = max(1, (int) round($l * $ratio));
            $nh = max(1, (int) round($h * $ratio));
            $reduite = imagecreatetruecolor($nl, $nh);
            // Conserve la transparence des PNG et WebP
            imagealphablending($reduite, false);
            imagesavealpha($reduite, true);
            imagecopyresampled($reduite, $image, 0, 0, 0, 0, $nl, $nh, imagesx($image), imagesy($image));
            $image = $reduite;
        }

        if (IMAGETYPE_JPEG === $type) {
            imageinterlace($image, true); // JPEG progressif : s'affiche plus vite sur mobile
        }
        $ok = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $chemin, self::QUALITE_JPEG),
            IMAGETYPE_PNG => imagepng($image, $chemin, 9),
            IMAGETYPE_WEBP => imagewebp($image, $chemin, self::QUALITE_WEBP),
        };
        if (!$ok) {
            throw new \RuntimeException(sprintf('Impossible d\'enregistrer la photo « %s ».', $chemin));
        }

        return ['largeur' => imagesx($image), 'hauteur' => imagesy($image), 'modifiee' => true];
    }

    /**
     * Écrit dans $destination une copie réduite à $cote × $cote au plus (proportions
     * conservées, même format). Renvoie false si l'image est illisible, ou déjà assez
     * petite : la photo elle-même sert alors de miniature.
     */
    public function miniature(string $source, string $destination, int $cote): bool
    {
        $infos = @getimagesize($source);
        if (false === $infos || max($infos[0], $infos[1]) <= $cote) {
            return false;
        }
        [$largeur, $hauteur, $type] = $infos;
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            default => false,
        };
        if (false === $image) {
            return false;
        }

        $ratio = $cote / max($largeur, $hauteur);
        $nl = max(1, (int) round($largeur * $ratio));
        $nh = max(1, (int) round($hauteur * $ratio));
        $reduite = imagecreatetruecolor($nl, $nh);
        imagealphablending($reduite, false);
        imagesavealpha($reduite, true);
        imagecopyresampled($reduite, $image, 0, 0, 0, 0, $nl, $nh, $largeur, $hauteur);

        $dossier = \dirname($destination);
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            throw new \RuntimeException(\sprintf('Impossible de créer le dossier « %s ».', $dossier));
        }
        if (IMAGETYPE_JPEG === $type) {
            imageinterlace($reduite, true);
        }

        return match ($type) {
            IMAGETYPE_JPEG => imagejpeg($reduite, $destination, self::QUALITE_JPEG),
            IMAGETYPE_PNG => imagepng($reduite, $destination, 9),
            IMAGETYPE_WEBP => imagewebp($reduite, $destination, self::QUALITE_WEBP),
        };
    }

    /** Angle de rotation à appliquer selon l'orientation EXIF (0 si aucune). */
    private function angleExif(string $chemin): int
    {
        if (!\function_exists('exif_read_data')) {
            return 0;
        }
        $exif = @exif_read_data($chemin);

        return match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
    }
}
