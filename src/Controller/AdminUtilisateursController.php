<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\ConfirmationSuppression;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

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
        private readonly ConfirmationSuppression $confirmation,
    ) {
    }

    #[Route('', name: 'admin_utilisateurs', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/utilisateurs.html.twig', ['lignes' => $this->utilisateurs->listeAdministration()]);
    }

    #[Route('/{id}/admin', name: 'admin_utilisateur_admin', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsCsrfTokenValid(new Expression('"utilisateur" ~ args["cible"].getId()'))]
    public function basculerAdmin(User $cible): Response
    {
        $this->refuserSiSupprime($cible);
        if ($cible->isAdmin()) {
            if ($this->dernierAdmin($cible)) {
                return $this->refuser('flash.utilisateur.dernier_admin');
            }
            $cible->setAdmin(false);
            $message = new TranslatableMessage('flash.utilisateur.plus_admin', ['pseudo' => $cible->getPseudo()]);
        } else {
            if (!$cible->isActif()) {
                return $this->refuser('flash.utilisateur.reactivez');
            }
            $cible->setAdmin(true);
            $message = new TranslatableMessage('flash.utilisateur.admin', ['pseudo' => $cible->getPseudo()]);
        }
        $this->em->flush();
        $this->addFlash('success', $message);

        // Un admin qui se retire lui-même le rôle perd l'accès à cette page
        return $cible === $this->getUser() ? $this->redirectToRoute('app_home') : $this->redirectToRoute('admin_utilisateurs');
    }

    #[Route('/{id}/actif', name: 'admin_utilisateur_actif', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsCsrfTokenValid(new Expression('"utilisateur" ~ args["cible"].getId()'))]
    public function basculerActif(User $cible): Response
    {
        $this->refuserSiSupprime($cible);
        if ($cible === $this->getUser()) {
            return $this->refuser('flash.utilisateur.pas_soi_desactiver');
        }
        if ($cible->isActif() && $this->dernierAdmin($cible)) {
            return $this->refuser('flash.utilisateur.dernier_admin');
        }
        $cible->setActif(!$cible->isActif());
        $this->em->flush();
        $this->addFlash('success', new TranslatableMessage($cible->isActif() ? 'flash.utilisateur.reactive' : 'flash.utilisateur.desactive', ['pseudo' => $cible->getPseudo()]));

        return $this->redirectToRoute('admin_utilisateurs');
    }

    #[Route('/{id}/supprimer', name: 'admin_utilisateur_supprimer', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsCsrfTokenValid(new Expression('"utilisateur" ~ args["cible"].getId()'), methods: ['POST'])]
    public function supprimer(User $cible, Request $request): Response
    {
        if ($cible->estSupprime()) {
            return $this->refuser('flash.utilisateur.deja_supprime');
        }
        if ($cible === $this->getUser()) {
            return $this->refuser('flash.utilisateur.pas_soi_supprimer');
        }
        if ($this->dernierAdmin($cible)) {
            return $this->refuser('flash.utilisateur.dernier_admin');
        }

        if ($request->isMethod('POST')) {
            if (!$this->confirmation->estConfirmee($request->getPayload()->getString('confirmation'))) {
                $this->addFlash('error', ConfirmationSuppression::annulation());

                return $this->redirectToRoute('admin_utilisateur_supprimer', ['id' => $cible->getId()]);
            }
            $pseudo = $cible->getPseudo();
            $cible->supprimer();
            $this->em->flush();
            $this->addFlash('success', new TranslatableMessage('flash.utilisateur.supprime', ['pseudo' => $pseudo]));

            return $this->redirectToRoute('admin_utilisateurs');
        }

        $ligne = $this->utilisateurs->ligneAdministration($cible);

        return $this->render('admin/utilisateur_supprimer.html.twig', ['cible' => $cible, 'ligne' => $ligne]);
    }

    private function refuserSiSupprime(User $cible): void
    {
        if ($cible->estSupprime()) {
            throw $this->createAccessDeniedException('Ce compte est supprimé.');
        }
    }

    /** La cible est le dernier administrateur actif. */
    private function dernierAdmin(User $cible): bool
    {
        return $cible->isAdmin() && $cible->isActif() && $this->utilisateurs->nombreAdminsActifs() <= 1;
    }

    private function refuser(string $cle): Response
    {
        $this->addFlash('error', new TranslatableMessage($cle));

        return $this->redirectToRoute('admin_utilisateurs');
    }
}
