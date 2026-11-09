<?php

namespace App\Asset;

use App\MiseAJour\VersionInstallee;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * Ajoute la version de GrimPanBloc aux feuilles de style et scripts servis par
 * asset() (« css/app.css?v=1.3.9 ») : après une mise à jour, l'adresse change et
 * le navigateur ne réutilise pas l'ancienne copie qu'il a gardée en cache.
 *
 * Les autres fichiers (photos, logo, polices) gardent leur adresse : leur contenu
 * ne change pas avec la version, et la police préchargée doit avoir la même
 * adresse que dans app.css.
 */
final class VersionApplication implements VersionStrategyInterface
{
    public function __construct(private readonly VersionInstallee $version)
    {
    }

    public function getVersion(string $path): string
    {
        return $this->version->get();
    }

    public function applyVersion(string $path): string
    {
        $chemin = (string) parse_url($path, \PHP_URL_PATH);
        if (!str_ends_with($chemin, '.css') && !str_ends_with($chemin, '.js')) {
            return $path;
        }

        return $path.(str_contains($path, '?') ? '&' : '?').'v='.rawurlencode($this->getVersion($path));
    }
}
