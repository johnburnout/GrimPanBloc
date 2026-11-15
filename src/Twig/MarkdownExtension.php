<?php

namespace App\Twig;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\MarkdownConverter;
use Twig\Attribute\AsTwigFilter;

/**
 * Filtre Twig « markdown » : texte saisi dans l'administration → HTML.
 *
 * Markdown GitHub (titres, listes, liens, tableaux, texte barré…). Le HTML écrit
 * dans le texte est affiché tel quel (échappé), jamais interprété, et les liens
 * javascript:, vbscript:, file: et data: sont neutralisés.
 */
final class MarkdownExtension
{
    private ?MarkdownConverter $convertisseur = null;

    #[AsTwigFilter('markdown', isSafe: ['html'])]
    public function enHtml(?string $texte): string
    {
        $this->convertisseur ??= new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);

        return $this->convertisseur->convert((string) $texte)->getContent();
    }
}
