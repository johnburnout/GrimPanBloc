<?php

namespace App\MiseAJour;

/**
 * Release publiée sur GitHub, avec son archive prête à installer.
 *
 * Convention (voir .github/workflows/release.yml) : chaque release porte
 * grimpanbloc-vX.Y.Z.zip et son empreinte grimpanbloc-vX.Y.Z.zip.sha256.
 * Depuis le changement de nom (1.3.0), elle porte aussi grimpepan-vX.Y.Z.zip(.sha256),
 * l'ancien nom, pour que les installations antérieures puissent se mettre à jour.
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

    /**
     * Construit la release à partir de la réponse de l'API GitHub.
     *
     * @param array<string, mixed> $d
     */
    public static function depuisApi(array $d): self
    {
        $tag = (string) ($d['tag_name'] ?? '');
        $archive = $empreinte = null;
        $taille = 0;
        $assets = [];
        foreach ($d['assets'] ?? [] as $a) {
            $assets[(string) ($a['name'] ?? '')] = $a;
        }
        // Nouveau nom d'abord, ancien nom (avant 1.3.0) à défaut
        foreach (['grimpanbloc', 'grimpepan'] as $prefixe) {
            if (isset($assets["$prefixe-$tag.zip"], $assets["$prefixe-$tag.zip.sha256"])) {
                $archive = (string) $assets["$prefixe-$tag.zip"]['browser_download_url'];
                $taille = (int) ($assets["$prefixe-$tag.zip"]['size'] ?? 0);
                $empreinte = (string) $assets["$prefixe-$tag.zip.sha256"]['browser_download_url'];
                break;
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
