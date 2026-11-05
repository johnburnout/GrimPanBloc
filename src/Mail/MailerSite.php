<?php

namespace App\Mail;

use App\Entity\User;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Envoie les mails du site avec le serveur SMTP configuré dans l'administration.
 * Envoi synchrone : les erreurs SMTP remontent tout de suite à l'administrateur.
 */
final class MailerSite
{
    public function __construct(private readonly ReglagesSmtp $reglages)
    {
    }

    /** Mail générique de vérification de la configuration SMTP. */
    public function envoyerTest(string $destinataire): void
    {
        $this->envoyer(
            (new Email())
                ->to($destinataire)
                ->subject('GrimpePan : mail de test')
                ->text("Bonjour,\n\nCe message confirme que GrimpePan peut envoyer des mails avec le serveur SMTP configuré.\n\nEnvoyé le ".date('d/m/Y à H:i').".\n\n— GrimpePan"),
        );
    }

    /**
     * Envoie le même message à chaque destinataire, un mail par personne
     * (les adresses ne sont jamais visibles des autres destinataires).
     *
     * @param list<User> $destinataires
     *
     * @return array{envoyes: int, erreurs: list<string>}
     */
    public function envoyerGroupe(array $destinataires, string $objet, string $corps): array
    {
        $mailer = $this->mailer();
        $envoyes = 0;
        $erreurs = [];
        foreach ($destinataires as $u) {
            try {
                $mailer->send($this->completer((new Email())
                    ->to(new Address((string) $u->getEmail(), (string) $u->getPseudo()))
                    ->subject($objet)
                    ->text(str_replace('{pseudo}', (string) $u->getPseudo(), $corps))));
                ++$envoyes;
            } catch (\Throwable $e) {
                $erreurs[] = $u->getPseudo().' : '.$e->getMessage();
            }
        }

        return ['envoyes' => $envoyes, 'erreurs' => $erreurs];
    }

    private function envoyer(Email $email): void
    {
        $this->mailer()->send($this->completer($email));
    }

    private function completer(Email $email): Email
    {
        $r = $this->reglages->lire();

        return $email->from(new Address($r['expediteur'], $r['expediteur_nom'] ?: 'GrimpePan'));
    }

    private function mailer(): Mailer
    {
        if (!$this->reglages->estConfigure()) {
            throw new \RuntimeException('Le serveur SMTP n\'est pas configuré (serveur et adresse d\'expédition requis).');
        }
        $r = $this->reglages->lire();
        $transport = new EsmtpTransport($r['hote'], $r['port'], 'ssl' === $r['chiffrement']);
        if ('aucun' === $r['chiffrement']) {
            $transport->setAutoTls(false);
        } elseif ('starttls' === $r['chiffrement']) {
            $transport->setRequireTls(true);
        }
        if ('' !== $r['utilisateur']) {
            $transport->setUsername($r['utilisateur']);
            $transport->setPassword($this->reglages->motDePasse());
        }

        return new Mailer($transport);
    }
}
