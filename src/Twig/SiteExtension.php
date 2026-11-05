<?php

namespace App\Twig;

use App\Site\IdentiteClub;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Twig\Attribute\AsTwigFunction;

/** Fonctions Twig : identité du club et QR code. */
final class SiteExtension
{
    public function __construct(private readonly IdentiteClub $club)
    {
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
