<?php

namespace App\Controller;

use App\Entity\User;
use App\EventSubscriber\ForcerHttpsSubscriber;
use App\Form\Reglages\ClubType;
use App\Form\Reglages\EnvoiGroupeType;
use App\Form\Reglages\HttpsType;
use App\Form\Reglages\MailTestType;
use App\Form\Reglages\SmtpType;
use App\Mail\MailerSite;
use App\Mail\ReglagesSmtp;
use App\Repository\ReglageRepository;
use App\Repository\UserRepository;
use App\Site\IdentiteClub;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Réglages du site. Une seule page (GET), cinq formulaires postant chacun vers
 * sa route ; un formulaire invalide est réaffiché avec ses erreurs, les autres
 * avec les valeurs enregistrées.
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminReglagesController extends AbstractController
{
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

    #[Route('/reglages', name: 'admin_reglages', methods: ['GET'])]
    public function reglages(Request $request, #[CurrentUser] User $admin): Response
    {
        return $this->page($request, $admin);
    }

    #[Route('/reglages/https', name: 'admin_reglages_https', methods: ['POST'])]
    public function https(Request $request, #[CurrentUser] User $admin): Response
    {
        $form = $this->formulaire('https', $admin)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $activer = (bool) $form->get('forcer_https')->getData();
            if ($activer && !$request->isSecure()) {
                // Garde-fou : si PHP ne voit pas le HTTPS, la redirection bouclerait.
                $form->addError(new FormError('Ouvrez d\'abord cette page en HTTPS (https://…) pour activer ce réglage.'));
            } else {
                $this->reglages->definir(ForcerHttpsSubscriber::CLE, $activer ? '1' : '0');
                $this->em->flush();

                return $this->enregistre($activer ? 'Le site utilise désormais toujours HTTPS.' : 'Redirection vers HTTPS désactivée.');
            }
        }

        return $this->page($request, $admin, $form);
    }

    #[Route('/reglages/club', name: 'admin_reglages_club', methods: ['POST'])]
    public function club(Request $request, #[CurrentUser] User $admin): Response
    {
        $form = $this->formulaire('club', $admin)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $donnees = $form->getData();
            try {
                if ($donnees['supprimer_logo']) {
                    $this->club->supprimerLogo();
                } elseif (null !== $donnees['logo']) {
                    $this->club->definirLogo($donnees['logo']);
                }
                $this->club->definirNom($donnees['nom_club']);

                return $this->enregistre('Identité du club enregistrée.');
            } catch (\InvalidArgumentException $e) {
                $form->get('logo')->addError(new FormError($e->getMessage()));
            }
        }

        return $this->page($request, $admin, $form);
    }

    #[Route('/reglages/smtp', name: 'admin_reglages_smtp', methods: ['POST'])]
    public function smtp(Request $request, #[CurrentUser] User $admin): Response
    {
        $form = $this->formulaire('smtp', $admin)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $saisi = (string) $form->get('mot_de_passe')->getData();
            // '' = effacé, null = inchangé
            $motDePasse = $form->get('effacer_mot_de_passe')->getData() ? '' : ('' === $saisi ? null : $saisi);
            $this->smtp->enregistrer($form->getData(), $motDePasse);

            return $this->enregistre('Réglages SMTP enregistrés.');
        }

        return $this->page($request, $admin, $form);
    }

    #[Route('/reglages/mail-test', name: 'admin_reglages_mail_test', methods: ['POST'])]
    public function mailTest(Request $request, #[CurrentUser] User $admin): Response
    {
        $form = $this->formulaire('mail_test', $admin)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $destinataire = $form->get('destinataire')->getData();
            try {
                $this->mailer->envoyerTest($destinataire);

                return $this->enregistre('Mail de test envoyé à '.$destinataire.'.');
            } catch (\Throwable $e) {
                $form->addError(new FormError('Échec de l\'envoi : '.$e->getMessage()));
            }
        }

        return $this->page($request, $admin, $form);
    }

    #[Route('/reglages/envoi-groupe', name: 'admin_reglages_envoi_groupe', methods: ['POST'])]
    public function envoiGroupe(Request $request, #[CurrentUser] User $admin): Response
    {
        $form = $this->formulaire('envoi_groupe', $admin)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            ['cible' => $cible, 'objet' => $objet, 'corps' => $corps] = $form->getData();
            $destinataires = $this->utilisateurs->actifs('admins' === $cible);
            set_time_limit(max(60, 5 * \count($destinataires)));
            try {
                $r = $this->mailer->envoyerGroupe($destinataires, $objet, $corps);
                if ($r['envoyes'] > 0) {
                    $this->addFlash('success', \sprintf('Message envoyé à %d destinataire(s).', $r['envoyes']));
                }
                if ([] === $r['erreurs']) {
                    return $this->redirectToRoute('admin_reglages');
                }
                foreach (\array_slice($r['erreurs'], 0, 10) as $erreur) {
                    $form->addError(new FormError('Échec pour '.$erreur));
                }
            } catch (\Throwable $e) {
                $form->addError(new FormError('Échec de l\'envoi : '.$e->getMessage()));
            }
        }

        return $this->page($request, $admin, $form);
    }

    /** Fiche A4 à imprimer : logo du club et QR code vers le site. */
    #[Route('/fiche', name: 'admin_fiche', methods: ['GET'])]
    public function fiche(): Response
    {
        return $this->render('admin/fiche.html.twig', [
            'urlSite' => $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    // ---------------------------------------------------------------------------

    /** Formulaire d'une section, prérempli avec les valeurs enregistrées. */
    private function formulaire(string $section, User $admin): FormInterface
    {
        [$type, $donnees] = match ($section) {
            'https' => [HttpsType::class, ['forcer_https' => '1' === $this->reglages->valeur(ForcerHttpsSubscriber::CLE)]],
            'club' => [ClubType::class, ['nom_club' => $this->club->nom(), 'supprimer_logo' => false, 'logo' => null]],
            'smtp' => [SmtpType::class, $this->smtp->lire()],
            'mail_test' => [MailTestType::class, ['destinataire' => $admin->getEmail()]],
            'envoi_groupe' => [EnvoiGroupeType::class, ['cible' => 'tous', 'objet' => '', 'corps' => '']],
        };

        return $this->createForm($type, $donnees, [
            'action' => $this->generateUrl('admin_reglages_'.$section),
            'csrf_token_id' => 'admin_reglages_'.$section,
            'csrf_message' => 'Le formulaire a expiré : rechargez la page et recommencez.',
        ]);
    }

    /** La page complète ; $soumis remplace le formulaire de sa section. */
    private function page(Request $request, User $admin, ?FormInterface $soumis = null): Response
    {
        $formulaires = [];
        foreach (['https', 'club', 'smtp', 'mail_test', 'envoi_groupe'] as $section) {
            $formulaires[$section] = $soumis && $soumis->getName() === $section ? $soumis : $this->formulaire($section, $admin);
        }

        return $this->render('admin/reglages.html.twig', $formulaires + [
            'forcerHttps' => '1' === $this->reglages->valeur(ForcerHttpsSubscriber::CLE),
            'enHttps' => $request->isSecure(),
            'clubLogo' => $this->club->logo(),
            'smtpDefini' => $this->smtp->lire()['mot_de_passe_defini'],
            'smtpConfigure' => $this->smtp->estConfigure(),
            'nbTous' => $this->utilisateurs->nombreActifs(),
            'nbAdmins' => $this->utilisateurs->nombreAdminsActifs(),
        ]);
    }

    private function enregistre(string $message): Response
    {
        $this->addFlash('success', $message);

        return $this->redirectToRoute('admin_reglages');
    }
}
