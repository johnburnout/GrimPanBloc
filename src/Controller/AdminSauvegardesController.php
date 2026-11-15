<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\TypeSauvegarde;
use App\Exception\Messages;
use App\Form\Sauvegardes\ConservationType;
use App\Form\Sauvegardes\ImportType;
use App\Repository\SecteurRepository;
use App\Sauvegarde\ArchiveSauvegarde;
use App\Sauvegarde\BilanRestauration;
use App\Sauvegarde\DepotSauvegardes;
use App\Sauvegarde\GestionSauvegardes;
use App\Sauvegarde\ReglageSauvegardes;
use App\Sauvegarde\Sauvegarde;
use App\Security\ConfirmationSuppression;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sauvegardes des secteurs, voies et photos : création, téléchargement, import,
 * restauration (complète ou d'un secteur) et suppression.
 */
#[Route('/admin/sauvegardes')]
#[IsGranted('ROLE_ADMIN')]
class AdminSauvegardesController extends AbstractController
{
    /** Mot à taper pour confirmer une restauration. */
    private const NOM = '[A-Za-z0-9-]+\.zip';

    public function __construct(
        private readonly GestionSauvegardes $gestion,
        private readonly DepotSauvegardes $depot,
        private readonly ReglageSauvegardes $reglage,
        private readonly ConfirmationSuppression $confirmation,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_sauvegardes', methods: ['GET'])]
    public function index(): Response
    {
        return $this->page();
    }

    #[Route('/creer', name: 'admin_sauvegarde_creer', methods: ['POST'])]
    #[IsCsrfTokenValid('sauvegarde_creer')]
    public function creer(Request $request): Response
    {
        try {
            $s = $this->gestion->creerManuelle(mb_substr(trim($request->getPayload()->getString('motif')), 0, 200));
            $this->addFlash('success', new TranslatableMessage('flash.sauvegarde.creee', ['taille' => $this->taille($s->taille)]));
        } catch (\Throwable $e) {
            $this->addFlash('error', new TranslatableMessage('flash.sauvegarde.echec', ['erreur' => Messages::de($e)]));
        }

        return $this->redirectToRoute('admin_sauvegardes');
    }

    #[Route('/importer', name: 'admin_sauvegarde_importer', methods: ['POST'])]
    public function importer(Request $request): Response
    {
        $form = $this->formulaireImport()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $s = $this->gestion->importer($form->get('archive')->getData());
                $this->addFlash('success', new TranslatableMessage('flash.sauvegarde.importee'));

                return $this->redirectToRoute('admin_sauvegarde_restaurer', ['nom' => $s->nom]);
            } catch (\InvalidArgumentException $e) {
                $form->get('archive')->addError(new FormError(Messages::texte($e, $this->translator)));
            }
        }

        return $this->page(import: $form);
    }

    #[Route('/conservation', name: 'admin_sauvegardes_conservation', methods: ['POST'])]
    public function conservation(Request $request): Response
    {
        $form = $this->formulaireConservation()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $n = (int) $form->get('automatiques')->getData();
            $this->reglage->definirAutomatiquesGardees($n);
            // Appliqué tout de suite, pas seulement à la prochaine sauvegarde automatique
            $supprimees = $this->depot->purgerAutomatiques($n);
            $this->addFlash('success', new TranslatableMessage('flash.sauvegarde.conservation', ['n' => $n, 'supprimees' => \count($supprimees)]));

            return $this->redirectToRoute('admin_sauvegardes');
        }

        return $this->page(conservation: $form);
    }

    #[Route('/{nom}/telecharger', name: 'admin_sauvegarde_telecharger', requirements: ['nom' => self::NOM], methods: ['GET'])]
    public function telecharger(string $nom): Response
    {
        $s = $this->trouver($nom);

        return (new BinaryFileResponse($s->chemin, headers: ['Cache-Control' => 'no-store']))
            ->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'grimpanbloc-'.$s->nom);
    }

    #[Route('/{nom}/restaurer', name: 'admin_sauvegarde_restaurer', requirements: ['nom' => self::NOM], methods: ['GET', 'POST'])]
    public function restaurer(string $nom, Request $request, ArchiveSauvegarde $archive, SecteurRepository $secteurs, #[CurrentUser] User $admin): Response
    {
        $s = $this->trouver($nom);
        try {
            $resume = $archive->lireResume($s->chemin);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', Messages::de($e));

            return $this->redirectToRoute('admin_sauvegardes');
        }

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            $retour = $this->redirectToRoute('admin_sauvegarde_restaurer', ['nom' => $nom]);
            if (!$this->isCsrfTokenValid('restaurer'.$nom, $payload->getString('_token'))) {
                $this->addFlash('error', new TranslatableMessage('commun.formulaire_expire'));

                return $retour;
            }
            if (!$this->confirmation->estConfirmee($payload->getString('confirmation'), ConfirmationSuppression::RESTAURER)) {
                $this->addFlash('error', ConfirmationSuppression::annulation('restauration', ConfirmationSuppression::RESTAURER));

                return $retour;
            }
            $portee = $payload->getString('portee');
            $secteur = 'tout' === $portee ? null : (ctype_digit($portee) ? (int) $portee : -1);
            if (-1 === $secteur || (null !== $secteur && null === $resume->secteur($secteur))) {
                $this->addFlash('error', new TranslatableMessage('flash.restauration.choisir'));

                return $retour;
            }
            // Sauvegarde manuelle : l'administrateur choisit de la garder ou non
            $supprimer = 'supprimer' === $payload->getString('apres');
            if (TypeSauvegarde::Manuelle === $s->type && !\in_array($payload->getString('apres'), ['garder', 'supprimer'], true)) {
                $this->addFlash('error', new TranslatableMessage('flash.restauration.garder'));

                return $retour;
            }

            try {
                ['avant' => $avant, 'bilan' => $bilan] = $this->gestion->restaurer($s, $secteur, $admin);
            } catch (\Throwable $e) {
                $this->addFlash('error', new TranslatableMessage('flash.restauration.echec', ['erreur' => Messages::de($e)]));

                return $retour;
            }
            $this->addFlash('success', $this->messageBilan($bilan, null === $secteur
                ? new TranslatableMessage('flash.restauration.tout')
                : new TranslatableMessage('flash.restauration.secteur', ['nom' => $resume->secteur($secteur)['nom'] ?? ''])));
            $this->addFlash('info', new TranslatableMessage('flash.restauration.etat_precedent', ['date' => $avant->creeLe->format('d/m/Y H:i:s')]));
            if ($supprimer && TypeSauvegarde::Manuelle === $s->type) {
                try {
                    $this->gestion->supprimer($s);
                    $this->addFlash('info', new TranslatableMessage('flash.restauration.supprimee'));
                } catch (\Throwable $e) {
                    $this->addFlash('error', new TranslatableMessage('flash.restauration.non_supprimee', ['erreur' => Messages::de($e)]));
                }
            }

            return null === $secteur ? $this->redirectToRoute('secteur_index') : $this->redirectToRoute('secteur_show', ['id' => $secteur]);
        }

        $existants = [];
        foreach ($secteurs->findAll() as $secteurActuel) {
            $existants[$secteurActuel->getId()] = $secteurActuel->getNom();
        }

        return $this->render('admin/sauvegarde_restaurer.html.twig', [
            'sauvegarde' => $s,
            'resume' => $resume,
            'existants' => $existants,
        ]);
    }

    #[Route('/{nom}/supprimer', name: 'admin_sauvegarde_supprimer', requirements: ['nom' => self::NOM], methods: ['POST'])]
    #[IsCsrfTokenValid(new Expression('"supprimer" ~ args["nom"]'))]
    public function supprimer(string $nom): Response
    {
        $s = $this->trouver($nom);
        try {
            $this->gestion->supprimer($s);
            $this->addFlash('success', new TranslatableMessage('flash.sauvegarde.supprimee', ['date' => $s->creeLe->format('d/m/Y H:i')]));
        } catch (\Throwable $e) {
            $this->addFlash('error', Messages::de($e));
        }

        return $this->redirectToRoute('admin_sauvegardes');
    }

    // ---------------------------------------------------------------------------

    private function trouver(string $nom): Sauvegarde
    {
        return $this->depot->trouver($nom) ?? throw $this->createNotFoundException('Sauvegarde introuvable.');
    }

    /**
     * @param FormInterface<array<string, mixed>>|null $import
     * @param FormInterface<array<string, mixed>>|null $conservation
     */
    private function page(?FormInterface $import = null, ?FormInterface $conservation = null): Response
    {
        return $this->render('admin/sauvegardes.html.twig', [
            'lignes' => $this->gestion->lister(),
            'tailleTotale' => $this->depot->tailleTotale(),
            'import' => $import ?? $this->formulaireImport(),
            'conservation' => $conservation ?? $this->formulaireConservation(),
            'automatiquesGardees' => $this->reglage->automatiquesGardees(),
            'tailleEnvoiMax' => ini_get('upload_max_filesize') ?: '?',
        ]);
    }

    /** @return FormInterface<array<string, mixed>> */
    private function formulaireImport(): FormInterface
    {
        return $this->createForm(ImportType::class, null, [
            'action' => $this->generateUrl('admin_sauvegarde_importer'),
            'csrf_message' => 'commun.formulaire_expire',
        ]);
    }

    /** @return FormInterface<array<string, mixed>> */
    private function formulaireConservation(): FormInterface
    {
        return $this->createForm(ConservationType::class, ['automatiques' => $this->reglage->automatiquesGardees()], [
            'action' => $this->generateUrl('admin_sauvegardes_conservation'),
            'csrf_message' => 'commun.formulaire_expire',
        ]);
    }

    private function messageBilan(BilanRestauration $b, TranslatableMessage $debut): TranslatableMessage
    {
        return new TranslatableMessage('flash.restauration.bilan', [
            'debut' => $debut, 'voies' => $b->voies, 'photos' => $b->photos, 'croix' => $b->croix,
            'ignorees' => $b->croixIgnorees, 'sans_fichier' => $b->photosSansFichier,
        ]);
    }

    /** Taille en Mo, écrite à la façon de la langue de la page (« 1,5 », « 1.5 »). */
    private function taille(int $octets): string
    {
        $format = new \NumberFormatter($this->translator->getLocale(), \NumberFormatter::DECIMAL);
        $format->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 1);
        $format->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 1);

        return (string) $format->format($octets / 1048576);
    }
}
