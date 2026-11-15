<?php

namespace App\Mail;

use App\Entity\User;
use App\Exception\EchecOperation;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Envoie les mails du site avec le serveur SMTP configuré dans l'administration.
 * Envoi synchrone : les erreurs SMTP remontent tout de suite à l'administrateur.
 */
final class MailerSite
{
    public function __construct(
        private readonly ReglagesSmtp $reglages,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** Mail générique de vérification de la configuration SMTP, dans la langue de l'administrateur. */
    public function envoyerTest(string $destinataire): void
    {
        $this->envoyer(
            (new Email())
                ->to($destinataire)
                ->subject($this->translator->trans('mail.test.objet'))
                ->text($this->translator->trans('mail.test.texte', ['date' => date('d/m/Y'), 'heure' => date('H:i')])),
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

        return $email->from(new Address($r['expediteur'], $r['expediteur_nom'] ?: 'GrimPanBloc'));
    }

    private function mailer(): Mailer
    {
        if (!$this->reglages->estConfigure()) {
            throw new EchecOperation('erreur.smtp_non_configure');
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
