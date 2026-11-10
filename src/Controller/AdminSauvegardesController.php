<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\TypeSauvegarde;
use App\Form\Sauvegardes\ConservationType;
use App\Form\Sauvegardes\ImportType;
use App\Repository\SecteurRepository;
use App\Sauvegarde\ArchiveSauvegarde;
use App\Sauvegarde\BilanRestauration;
use App\Sauvegarde\DepotSauvegardes;
use App\Sauvegarde\GestionSauvegardes;
use App\Sauvegarde\ReglageSauvegardes;
use App\Sauvegarde\Sauvegarde;
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

/**
 * Sauvegardes des secteurs, voies et photos : création, téléchargement, import,
 * restauration (complète ou d'un secteur) et suppression.
 */
#[Route('/admin/sauvegardes')]
#[IsGranted('ROLE_ADMIN')]
class AdminSauvegardesController extends AbstractController
{
    /** Mot à taper pour confirmer une restauration. */
    public const MOT_RESTAURATION = 'RESTAURER';
    private const NOM = '[A-Za-z0-9-]+\.zip';

    public function __construct(
        private readonly GestionSauvegardes $gestion,
        private readonly DepotSauvegardes $depot,
        private readonly ReglageSauvegardes $reglage,
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
            $this->addFlash('success', \sprintf('Sauvegarde créée (%s).', $this->taille($s->taille)));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'La sauvegarde a échoué : '.$e->getMessage());
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
                $this->addFlash('success', 'Archive importée : vous pouvez maintenant la restaurer.');

                return $this->redirectToRoute('admin_sauvegarde_restaurer', ['nom' => $s->nom]);
            } catch (\InvalidArgumentException $e) {
                $form->get('archive')->addError(new FormError($e->getMessage()));
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
            $this->addFlash('success', \sprintf('Les %d dernières sauvegardes automatiques seront conservées.', $n)
                .($supprimees ? \sprintf(' %d sauvegarde(s) plus ancienne(s) supprimée(s).', \count($supprimees)) : ''));

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
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('admin_sauvegardes');
        }

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            $retour = $this->redirectToRoute('admin_sauvegarde_restaurer', ['nom' => $nom]);
            if (!$this->isCsrfTokenValid('restaurer'.$nom, $payload->getString('_token'))) {
                $this->addFlash('error', 'Le formulaire a expiré : rechargez la page et recommencez.');

                return $retour;
            }
            if (self::MOT_RESTAURATION !== trim($payload->getString('confirmation'))) {
                $this->addFlash('error', 'Restauration annulée : tapez '.self::MOT_RESTAURATION.' pour confirmer.');

                return $retour;
            }
            $portee = $payload->getString('portee');
            $secteur = 'tout' === $portee ? null : (ctype_digit($portee) ? (int) $portee : -1);
            if (-1 === $secteur || (null !== $secteur && null === $resume->secteur($secteur))) {
                $this->addFlash('error', 'Choisissez ce qu\'il faut restaurer.');

                return $retour;
            }
            // Sauvegarde manuelle : l'administrateur choisit de la garder ou non
            $supprimer = 'supprimer' === $payload->getString('apres');
            if (TypeSauvegarde::Manuelle === $s->type && !\in_array($payload->getString('apres'), ['garder', 'supprimer'], true)) {
                $this->addFlash('error', 'Indiquez s\'il faut garder cette sauvegarde après la restauration.');

                return $retour;
            }

            try {
                ['avant' => $avant, 'bilan' => $bilan] = $this->gestion->restaurer($s, $secteur, $admin);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'La restauration a échoué, rien n\'a été modifié : '.$e->getMessage());

                return $retour;
            }
            $this->addFlash('success', $this->messageBilan($bilan, null === $secteur ? 'Sauvegarde restaurée' : \sprintf('Secteur « %s » restauré', $resume->secteur($secteur)['nom'] ?? '')));
            $this->addFlash('info', \sprintf('L\'état précédent a été sauvegardé automatiquement (%s).', $avant->creeLe->format('d/m/Y H:i:s')));
            if ($supprimer && TypeSauvegarde::Manuelle === $s->type) {
                try {
                    $this->gestion->supprimer($s);
                    $this->addFlash('info', 'La sauvegarde restaurée a été supprimée.');
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'La sauvegarde restaurée n\'a pas pu être supprimée : '.$e->getMessage());
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
            'mot' => self::MOT_RESTAURATION,
        ]);
    }

    #[Route('/{nom}/supprimer', name: 'admin_sauvegarde_supprimer', requirements: ['nom' => self::NOM], methods: ['POST'])]
    #[IsCsrfTokenValid(new Expression('"supprimer" ~ args["nom"]'))]
    public function supprimer(string $nom): Response
    {
        $s = $this->trouver($nom);
        try {
            $this->gestion->supprimer($s);
            $this->addFlash('success', \sprintf('Sauvegarde du %s supprimée.', $s->creeLe->format('d/m/Y H:i')));
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
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
            'csrf_message' => 'Le formulaire a expiré : rechargez la page et recommencez.',
        ]);
    }

    /** @return FormInterface<array<string, mixed>> */
    private function formulaireConservation(): FormInterface
    {
        return $this->createForm(ConservationType::class, ['automatiques' => $this->reglage->automatiquesGardees()], [
            'action' => $this->generateUrl('admin_sauvegardes_conservation'),
            'csrf_message' => 'Le formulaire a expiré : rechargez la page et recommencez.',
        ]);
    }

    private function messageBilan(BilanRestauration $b, string $debut): string
    {
        $message = \sprintf('%s : %d voie(s), %d photo(s) et %d croix.', $debut, $b->voies, $b->photos, $b->croix);
        if ($b->croixIgnorees > 0) {
            $message .= \sprintf(' %d croix ignorée(s) : grimpeur absent de ce site.', $b->croixIgnorees);
        }
        if ($b->photosSansFichier > 0) {
            $message .= \sprintf(' %d photo(s) sans fichier dans la sauvegarde.', $b->photosSansFichier);
        }

        return $message;
    }

    private function taille(int $octets): string
    {
        return number_format($octets / 1048576, 1, ',', ' ').' Mo';
    }
}
