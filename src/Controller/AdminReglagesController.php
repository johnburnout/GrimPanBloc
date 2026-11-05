<?php

namespace App\Controller;

use App\Entity\User;
use App\EventSubscriber\ForcerHttpsSubscriber;
use App\Repository\ReglageRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Mail\MailerSite;
use App\Mail\ReglagesSmtp;
use App\Repository\UserRepository;
use App\Site\IdentiteClub;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Réglages du site : serveur SMTP, mail de test, envoi d'un message aux utilisateurs. */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminReglagesController extends AbstractController
{
    public const LONGUEUR_MAX_CORPS = 20000;

    public function __construct(
        private readonly ReglagesSmtp $smtp,
        private readonly MailerSite $mailer,
        private readonly UserRepository $utilisateurs,
        private readonly IdentiteClub $club,
        private readonly ReglageRepository $reglages,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'admin', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_reglages');
    }

    #[Route('/reglages', name: 'admin_reglages', methods: ['GET', 'POST'])]
    public function reglages(Request $request): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();
        $p = $request->getPayload();
        $saisie = ['destinataire' => $admin->getEmail(), 'objet' => '', 'corps' => '', 'cible' => 'tous'];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_reglages', $p->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            switch ($p->getString('action')) {
                case 'https':
                    $activer = $p->getBoolean('forcer_https');
                    if ($activer && !$request->isSecure()) {
                        // Garde-fou : si PHP ne voit pas le HTTPS, la redirection bouclerait.
                        $this->addFlash('error', 'Ouvrez d\'abord cette page en HTTPS (https://…) pour activer ce réglage.');
                        break;
                    }
                    $this->reglages->definir(ForcerHttpsSubscriber::CLE, $activer ? '1' : '0');
                    $this->em->flush();
                    $this->addFlash('success', $activer ? 'Le site utilise désormais toujours HTTPS.' : 'Redirection vers HTTPS désactivée.');

                    return $this->redirectToRoute('admin_reglages');

                case 'club':
                    $this->club->definirNom($p->getString('nom_club'));
                    try {
                        if ($p->getBoolean('supprimer_logo')) {
                            $this->club->supprimerLogo();
                        } elseif ($fichier = $request->files->get('logo')) {
                            if (!$fichier->isValid()) {
                                throw new \InvalidArgumentException('Envoi du logo impossible : '.$fichier->getErrorMessage());
                            }
                            if ($fichier->getSize() > 5 * 1024 * 1024) {
                                throw new \InvalidArgumentException('Le logo ne doit pas dépasser 5 Mo.');
                            }
                            $this->club->definirLogo($fichier);
                        }
                        $this->addFlash('success', 'Identité du club enregistrée.');

                        return $this->redirectToRoute('admin_reglages');
                    } catch (\InvalidArgumentException $e) {
                        $this->addFlash('error', $e->getMessage());
                    }
                    break;

                case 'smtp':
                    $hote = trim($p->getString('hote'));
                    $expediteur = trim($p->getString('expediteur'));
                    $port = $p->getInt('port');
                    if ('' !== $hote && !preg_match('/^[A-Za-z0-9.\-]+$/', $hote)) {
                        $this->addFlash('error', 'Nom de serveur SMTP invalide.');
                    } elseif ('' !== $expediteur && !filter_var($expediteur, FILTER_VALIDATE_EMAIL)) {
                        $this->addFlash('error', 'Adresse d\'expédition invalide.');
                    } elseif ($port < 1 || $port > 65535) {
                        $this->addFlash('error', 'Port invalide.');
                    } else {
                        $motDePasse = $p->getBoolean('effacer_mot_de_passe') ? '' : ('' === $p->getString('mot_de_passe') ? null : $p->getString('mot_de_passe'));
                        $this->smtp->enregistrer([
                            'hote' => $hote, 'port' => $port, 'chiffrement' => $p->getString('chiffrement'),
                            'utilisateur' => $p->getString('utilisateur'), 'expediteur' => $expediteur,
                            'expediteur_nom' => $p->getString('expediteur_nom'),
                        ], $motDePasse);
                        $this->addFlash('success', 'Réglages SMTP enregistrés.');

                        return $this->redirectToRoute('admin_reglages');
                    }
                    break;

                case 'test':
                    $saisie['destinataire'] = trim($p->getString('destinataire'));
                    if (!filter_var($saisie['destinataire'], FILTER_VALIDATE_EMAIL)) {
                        $this->addFlash('error', 'Adresse du destinataire invalide.');
                        break;
                    }
                    try {
                        $this->mailer->envoyerTest($saisie['destinataire']);
                        $this->addFlash('success', 'Mail de test envoyé à '.$saisie['destinataire'].'.');

                        return $this->redirectToRoute('admin_reglages');
                    } catch (\Throwable $e) {
                        $this->addFlash('error', 'Échec de l\'envoi : '.$e->getMessage());
                    }
                    break;

                case 'envoi_groupe':
                    $saisie = ['objet' => trim($p->getString('objet')), 'corps' => trim($p->getString('corps')),
                        'cible' => 'admins' === $p->getString('cible') ? 'admins' : 'tous'] + $saisie;
                    if ('' === $saisie['objet'] || '' === $saisie['corps']) {
                        $this->addFlash('error', 'L\'objet et le message sont obligatoires.');
                        break;
                    }
                    if (mb_strlen($saisie['corps']) > self::LONGUEUR_MAX_CORPS || mb_strlen($saisie['objet']) > 200) {
                        $this->addFlash('error', 'Message trop long.');
                        break;
                    }
                    $destinataires = $this->utilisateurs->actifs('admins' === $saisie['cible']);
                    set_time_limit(max(60, 5 * \count($destinataires)));
                    try {
                        $r = $this->mailer->envoyerGroupe($destinataires, $saisie['objet'], $saisie['corps']);
                    } catch (\Throwable $e) {
                        $this->addFlash('error', 'Échec de l\'envoi : '.$e->getMessage());
                        break;
                    }
                    if ($r['envoyes'] > 0) {
                        $this->addFlash('success', sprintf('Message envoyé à %d destinataire(s).', $r['envoyes']));
                    }
                    foreach (\array_slice($r['erreurs'], 0, 10) as $erreur) {
                        $this->addFlash('error', 'Échec pour '.$erreur);
                    }
                    if ([] === $r['erreurs']) {
                        return $this->redirectToRoute('admin_reglages');
                    }
                    break;

                default:
                    throw $this->createAccessDeniedException('Action inconnue.');
            }
        }

        return $this->render('admin/reglages.html.twig', [
            'clubNom' => $this->club->nom(),
            'forcerHttps' => '1' === $this->reglages->valeur(ForcerHttpsSubscriber::CLE),
            'enHttps' => $request->isSecure(),
            'clubLogo' => $this->club->logo(),
            'smtp' => $this->smtp->lire(),
            'smtpConfigure' => $this->smtp->estConfigure(),
            'chiffrements' => ReglagesSmtp::CHIFFREMENTS,
            'saisie' => $saisie,
            'nbTous' => \count($this->utilisateurs->actifs()),
            'nbAdmins' => $this->utilisateurs->nombreAdminsActifs(),
        ]);
    }

    /** Fiche A4 à imprimer : logo du club et QR code vers le site. */
    #[Route('/fiche', name: 'admin_fiche', methods: ['GET'])]
    public function fiche(): Response
    {
        return $this->render('admin/fiche.html.twig', [
            'urlSite' => $this->generateUrl('app_home', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }
}
