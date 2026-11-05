<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Liste des utilisateurs : nommer / retirer un admin, désactiver / réactiver,
 * supprimer (le pseudo reste affiché sur ses voies, voir User::supprimer()).
 * Il reste toujours au moins un administrateur actif.
 */
#[Route('/admin/utilisateurs')]
#[IsGranted('ROLE_ADMIN')]
class AdminUtilisateursController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $utilisateurs,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'admin_utilisateurs', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/utilisateurs.html.twig', ['lignes' => $this->utilisateurs->listeAdministration()]);
    }

    #[Route('/{id}/admin', name: 'admin_utilisateur_admin', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function basculerAdmin(User $cible, Request $request): Response
    {
        $this->verifier($request, $cible);
        if ($cible->isAdmin()) {
            if ($this->dernierAdmin($cible)) {
                return $this->refuser('Il doit rester au moins un administrateur actif.');
            }
            $cible->setAdmin(false);
            $message = sprintf('%s n\'est plus administrateur.', $cible->getPseudo());
        } else {
            if (!$cible->isActif()) {
                return $this->refuser('Réactivez d\'abord ce compte.');
            }
            $cible->setAdmin(true);
            $message = sprintf('%s est maintenant administrateur.', $cible->getPseudo());
        }
        $this->em->flush();
        $this->addFlash('success', $message);

        // Un admin qui se retire lui-même le rôle perd l'accès à cette page
        return $cible === $this->getUser() ? $this->redirectToRoute('app_home') : $this->redirectToRoute('admin_utilisateurs');
    }

    #[Route('/{id}/actif', name: 'admin_utilisateur_actif', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function basculerActif(User $cible, Request $request): Response
    {
        $this->verifier($request, $cible);
        if ($cible === $this->getUser()) {
            return $this->refuser('Vous ne pouvez pas désactiver votre propre compte.');
        }
        if ($cible->isActif() && $this->dernierAdmin($cible)) {
            return $this->refuser('Il doit rester au moins un administrateur actif.');
        }
        $cible->setActif(!$cible->isActif());
        $this->em->flush();
        $this->addFlash('success', sprintf('Compte de %s %s.', $cible->getPseudo(), $cible->isActif() ? 'réactivé' : 'désactivé : il ne peut plus se connecter'));

        return $this->redirectToRoute('admin_utilisateurs');
    }

    #[Route('/{id}/supprimer', name: 'admin_utilisateur_supprimer', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function supprimer(User $cible, Request $request): Response
    {
        if ($cible->estSupprime()) {
            return $this->refuser('Ce compte est déjà supprimé.');
        }
        if ($cible === $this->getUser()) {
            return $this->refuser('Vous ne pouvez pas supprimer votre propre compte.');
        }
        if ($this->dernierAdmin($cible)) {
            return $this->refuser('Il doit rester au moins un administrateur actif.');
        }

        if ($request->isMethod('POST')) {
            $this->verifier($request, $cible);
            if (VoieController::MOT_CONFIRMATION !== trim($request->getPayload()->getString('confirmation'))) {
                $this->addFlash('error', 'Suppression annulée : tapez '.VoieController::MOT_CONFIRMATION.' pour confirmer.');

                return $this->redirectToRoute('admin_utilisateur_supprimer', ['id' => $cible->getId()]);
            }
            $pseudo = $cible->getPseudo();
            $cible->supprimer();
            $this->em->flush();
            $this->addFlash('success', sprintf('Compte de %s supprimé. Ses voies restent visibles sous son pseudo.', $pseudo));

            return $this->redirectToRoute('admin_utilisateurs');
        }

        $ligne = array_values(array_filter($this->utilisateurs->listeAdministration(), fn ($l) => $l['utilisateur'] === $cible))[0] ?? null;

        return $this->render('admin/utilisateur_supprimer.html.twig', ['cible' => $cible, 'ligne' => $ligne]);
    }

    private function verifier(Request $request, User $cible): void
    {
        if (!$this->isCsrfTokenValid('utilisateur'.$cible->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        if ($cible->estSupprime()) {
            throw $this->createAccessDeniedException('Ce compte est supprimé.');
        }
    }

    /** La cible est le dernier administrateur actif. */
    private function dernierAdmin(User $cible): bool
    {
        return $cible->isAdmin() && $cible->isActif() && $this->utilisateurs->nombreAdminsActifs() <= 1;
    }

    private function refuser(string $message): Response
    {
        $this->addFlash('error', $message);

        return $this->redirectToRoute('admin_utilisateurs');
    }
}
