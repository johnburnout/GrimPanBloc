<?php

namespace App\MiseAJour;

/**
 * Release publiée sur GitHub, avec son archive prête à installer.
 *
 * Convention (voir .github/workflows/release.yml) : chaque release porte
 * deux fichiers, grimpepan-vX.Y.Z.zip et grimpepan-vX.Y.Z.zip.sha256.
 */
final readonly class Release
{
    public function __construct(
        public string $version,        // « 1.2.0 »
        public string $tag,            // « v1.2.0 »
        public string $nom,
        public string $notes,          // notes de version (Markdown)
        public string $url,            // page de la release sur GitHub
        public ?\DateTimeImmutable $publieeLe,
        public ?string $urlArchive,
        public ?string $urlEmpreinte,
        public int $tailleArchive,
    ) {
    }

    /** Construit la release à partir de la réponse de l'API GitHub. */
    public static function depuisApi(array $d): self
    {
        $tag = (string) ($d['tag_name'] ?? '');
        $archive = $empreinte = null;
        $taille = 0;
        foreach ($d['assets'] ?? [] as $a) {
            $nom = (string) ($a['name'] ?? '');
            if ("grimpepan-$tag.zip" === $nom) {
                $archive = (string) $a['browser_download_url'];
                $taille = (int) ($a['size'] ?? 0);
            } elseif ("grimpepan-$tag.zip.sha256" === $nom) {
                $empreinte = (string) $a['browser_download_url'];
            }
        }

        return new self(
            ltrim($tag, 'vV'),
            $tag,
            (string) ($d['name'] ?? $tag) ?: $tag,
            (string) ($d['body'] ?? ''),
            (string) ($d['html_url'] ?? ''),
            isset($d['published_at']) ? new \DateTimeImmutable($d['published_at']) : null,
            $archive,
            $empreinte,
            $taille,
        );
    }

    /** L'archive et son empreinte sont présentes : la mise à jour en un clic est possible. */
    public function estInstallable(): bool
    {
        return null !== $this->urlArchive && null !== $this->urlEmpreinte;
    }
}
