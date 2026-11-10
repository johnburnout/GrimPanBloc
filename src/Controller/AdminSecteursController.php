<?php

namespace App\Controller;

use App\Entity\Secteur;
use App\Entity\User;
use App\Repository\SecteurRepository;
use App\Sauvegarde\GestionSauvegardes;
use App\Security\ConfirmationSuppression;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Nettoyage d'un secteur : toutes ses voies et toutes ses photos (même inutilisées)
 * sont supprimées, après une sauvegarde automatique. Le secteur lui-même reste.
 */
#[Route('/admin/secteurs')]
#[IsGranted('ROLE_ADMIN')]
class AdminSecteursController extends AbstractController
{
    #[Route('/{id}/nettoyer', name: 'admin_secteur_nettoyer', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function nettoyer(Secteur $secteur, Request $request, SecteurRepository $secteurs, GestionSauvegardes $sauvegardes, #[CurrentUser] User $admin): Response
    {
        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            if (!$this->isCsrfTokenValid('nettoyer'.$secteur->getId(), $payload->getString('_token'))) {
                $this->addFlash('error', 'Le formulaire a expiré : rechargez la page et recommencez.');
            } elseif (!ConfirmationSuppression::estConfirmee($payload->getString('confirmation'))) {
                $this->addFlash('error', 'Nettoyage annulé : tapez '.ConfirmationSuppression::MOT.' pour confirmer.');
            } else {
                $nom = $secteur->getNom();
                try {
                    ['avant' => $avant, 'bilan' => $bilan] = $sauvegardes->nettoyerSecteur($secteur, $admin);
                    $this->addFlash('success', \sprintf('Secteur « %s » nettoyé : %d voie(s) et %d photo(s) supprimée(s).', $nom, $bilan->voiesSupprimees, $bilan->photosSupprimees));
                    $this->addFlash('info', \sprintf('Sauvegarde faite juste avant (%s) : en cas d\'erreur, restaurez ce secteur depuis Administration → Sauvegardes.', $avant->creeLe->format('d/m/Y H:i:s')));

                    return $this->redirectToRoute('secteur_show', ['id' => $secteur->getId()]);
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Le nettoyage a échoué, rien n\'a été supprimé : '.$e->getMessage());
                }
            }

            return $this->redirectToRoute('admin_secteur_nettoyer', ['id' => $secteur->getId()]);
        }

        return $this->render('admin/secteur_nettoyer.html.twig', [
            'secteur' => $secteur,
            'contenu' => $secteurs->contenu($secteur),
        ]);
    }
}
