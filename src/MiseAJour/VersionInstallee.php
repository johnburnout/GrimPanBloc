<?php

namespace App\MiseAJour;

/** Version de GrimPanBloc installée, lue dans le fichier VERSION à la racine. */
final class VersionInstallee
{
    private ?string $version = null;

    public function __construct(private readonly string $racine)
    {
    }

    public function get(): string
    {
        return $this->version ??= trim((string) @file_get_contents($this->racine.'/VERSION')) ?: '0.0.0';
    }

    /** La version $autre est plus récente que celle installée. */
    public function estDepasseePar(string $autre): bool
    {
        return version_compare($autre, $this->get(), '>');
    }
}
