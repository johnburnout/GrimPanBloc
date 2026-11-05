<?php

namespace App\Mail;

use App\Repository\ReglageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Réglages SMTP du site, stockés en base (table « reglage »).
 * Le mot de passe est chiffré avec une clé dérivée d'APP_SECRET : si APP_SECRET
 * change, il faut le ressaisir.
 */
final class ReglagesSmtp
{
    public const CHIFFREMENTS = ['starttls' => 'STARTTLS (port 587)', 'ssl' => 'SSL/TLS (port 465)', 'aucun' => 'Aucun (déconseillé)'];

    public function __construct(
        private readonly ReglageRepository $reglages,
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.secret%')] private readonly string $secret,
    ) {
    }

    /** @return array{hote: string, port: int, chiffrement: string, utilisateur: string, expediteur: string, expediteur_nom: string, mot_de_passe_defini: bool} */
    public function lire(): array
    {
        return [
            'hote' => (string) $this->reglages->valeur('smtp.hote', ''),
            'port' => (int) $this->reglages->valeur('smtp.port', '587'),
            'chiffrement' => (string) $this->reglages->valeur('smtp.chiffrement', 'starttls'),
            'utilisateur' => (string) $this->reglages->valeur('smtp.utilisateur', ''),
            'expediteur' => (string) $this->reglages->valeur('smtp.expediteur', ''),
            'expediteur_nom' => (string) $this->reglages->valeur('smtp.expediteur_nom', 'GrimPanBloc'),
            'mot_de_passe_defini' => '' !== (string) $this->reglages->valeur('smtp.mot_de_passe', ''),
        ];
    }

    public function motDePasse(): string
    {
        $chiffre = (string) $this->reglages->valeur('smtp.mot_de_passe', '');
        if ('' === $chiffre) {
            return '';
        }
        $brut = base64_decode($chiffre, true);
        if (false === $brut || \strlen($brut) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }
        $clair = sodium_crypto_secretbox_open(substr($brut, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($brut, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->cle());

        return false === $clair ? '' : $clair;
    }

    public function estConfigure(): bool
    {
        $r = $this->lire();

        return '' !== $r['hote'] && '' !== $r['expediteur'];
    }

    /**
     * @param array{hote: string, port: int|string, chiffrement: string, utilisateur: string, expediteur: string, expediteur_nom: string} $r
     * @param ?string $motDePasse null = inchangé, '' = effacé
     */
    public function enregistrer(array $r, ?string $motDePasse): void
    {
        $this->reglages->definir('smtp.hote', trim($r['hote']));
        $this->reglages->definir('smtp.port', (string) (int) $r['port']);
        $this->reglages->definir('smtp.chiffrement', \array_key_exists($r['chiffrement'], self::CHIFFREMENTS) ? $r['chiffrement'] : 'starttls');
        $this->reglages->definir('smtp.utilisateur', trim($r['utilisateur']));
        $this->reglages->definir('smtp.expediteur', trim($r['expediteur']));
        $this->reglages->definir('smtp.expediteur_nom', trim($r['expediteur_nom']));
        if (null !== $motDePasse) {
            $this->reglages->definir('smtp.mot_de_passe', '' === $motDePasse ? '' : $this->chiffrer($motDePasse));
        }
        $this->em->flush();
    }

    private function chiffrer(string $clair): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($clair, $nonce, $this->cle()));
    }

    private function cle(): string
    {
        // Ne pas modifier ce préfixe (ancien nom de l'application) : il sert à dériver la clé
        // qui chiffre les mots de passe SMTP déjà enregistrés.
        return sodium_crypto_generichash('grimpepan-smtp|'.$this->secret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
