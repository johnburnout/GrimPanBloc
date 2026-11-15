<?php

namespace App\Twig;

use App\Locale\Langues;
use App\Site\IdentiteClub;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/** Fonctions Twig : identité du club, langues de l'interface et QR code. */
final class SiteExtension
{
    public function __construct(
        private readonly IdentiteClub $club,
        private readonly Langues $langues,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** Nombre écrit à la façon de la langue de la page (« 1 250,5 », « 1,250.5 »). */
    #[AsTwigFilter('nombre')]
    public function nombre(int|float $valeur, int $decimales = 0): string
    {
        $format = new \NumberFormatter($this->translator->getLocale(), \NumberFormatter::DECIMAL);
        $format->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimales);
        $format->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimales);

        return (string) $format->format($valeur);
    }

    /** @return array<string, string> code → nom de la langue (dans cette langue) */
    #[AsTwigFunction('langues')]
    public function langues(): array
    {
        return $this->langues->toutes();
    }

    #[AsTwigFunction('club_nom')]
    public function nom(): string
    {
        return $this->club->nom();
    }

    #[AsTwigFunction('club_logo')]
    public function logo(): ?string
    {
        return $this->club->logo();
    }

    /** QR code SVG (à afficher avec |raw : généré par la bibliothèque, sans donnée de l'utilisateur). */
    #[AsTwigFunction('qr_code_svg', isSafe: ['html'])]
    public function qrCode(string $texte): string
    {
        return (new QRCode(new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
        ])))->render($texte);
    }
}
