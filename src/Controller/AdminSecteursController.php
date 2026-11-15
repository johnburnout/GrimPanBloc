<?php

namespace App\Controller;

use App\Entity\Secteur;
use App\Entity\User;
use App\Exception\Messages;
use App\Repository\SecteurRepository;
use App\Sauvegarde\GestionSauvegardes;
use App\Security\ConfirmationSuppression;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Nettoyage d'un secteur : toutes ses voies et toutes ses photos (même inutilisées)
 * sont supprimées, après une sauvegarde automatique. Le secteur lui-même reste.
 *
 * Suppression d'un secteur : la même chose, puis le secteur lui-même.
 */
#[Route('/admin/secteurs')]
#[IsGranted('ROLE_ADMIN')]
class AdminSecteursController extends AbstractController
{
    #[Route('/{id}/nettoyer', name: 'admin_secteur_nettoyer', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function nettoyer(Secteur $secteur, Request $request, SecteurRepository $secteurs, GestionSauvegardes $sauvegardes, ConfirmationSuppression $confirmation, #[CurrentUser] User $admin): Response
    {
        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            if (!$this->isCsrfTokenValid('nettoyer'.$secteur->getId(), $payload->getString('_token'))) {
                $this->addFlash('error', new TranslatableMessage('commun.formulaire_expire'));
            } elseif (!$confirmation->estConfirmee($payload->getString('confirmation'))) {
                $this->addFlash('error', ConfirmationSuppression::annulation('nettoyage'));
            } else {
                $nom = $secteur->getNom();
                try {
                    ['avant' => $avant, 'bilan' => $bilan] = $sauvegardes->nettoyerSecteur($secteur, $admin);
                    $this->addFlash('success', new TranslatableMessage('flash.secteur.nettoye', ['nom' => $nom, 'voies' => $bilan->voiesSupprimees, 'photos' => $bilan->photosSupprimees]));
                    $this->addFlash('info', new TranslatableMessage('flash.secteur.sauvegarde_avant', ['date' => $avant->creeLe->format('d/m/Y H:i:s')]));

                    return $this->redirectToRoute('secteur_show', ['id' => $secteur->getId()]);
                } catch (\Throwable $e) {
                    $this->addFlash('error', new TranslatableMessage('flash.secteur.echec_nettoyage', ['erreur' => Messages::de($e)]));
                }
            }

            return $this->redirectToRoute('admin_secteur_nettoyer', ['id' => $secteur->getId()]);
        }

        return $this->render('admin/secteur_nettoyer.html.twig', [
            'secteur' => $secteur,
            'contenu' => $secteurs->contenu($secteur),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_secteur_supprimer', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function supprimer(Secteur $secteur, Request $request, SecteurRepository $secteurs, GestionSauvegardes $sauvegardes, ConfirmationSuppression $confirmation, #[CurrentUser] User $admin): Response
    {
        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            if (!$this->isCsrfTokenValid('supprimer-secteur'.$secteur->getId(), $payload->getString('_token'))) {
                $this->addFlash('error', new TranslatableMessage('commun.formulaire_expire'));
            } elseif (!$confirmation->estConfirmee($payload->getString('confirmation'))) {
                $this->addFlash('error', ConfirmationSuppression::annulation());
            } else {
                $nom = $secteur->getNom();
                try {
                    ['avant' => $avant, 'bilan' => $bilan] = $sauvegardes->supprimerSecteur($secteur, $admin);
                    $this->addFlash('success', new TranslatableMessage('flash.secteur.supprime', ['nom' => $nom, 'voies' => $bilan->voiesSupprimees, 'photos' => $bilan->photosSupprimees]));
                    $this->addFlash('info', new TranslatableMessage('flash.secteur.sauvegarde_avant', ['date' => $avant->creeLe->format('d/m/Y H:i:s')]));

                    return $this->redirectToRoute('secteur_index');
                } catch (\Throwable $e) {
                    $this->addFlash('error', new TranslatableMessage('flash.secteur.echec_suppression', ['erreur' => Messages::de($e)]));
                }
            }

            return $this->redirectToRoute('admin_secteur_supprimer', ['id' => $secteur->getId()]);
        }

        return $this->render('admin/secteur_supprimer.html.twig', [
            'secteur' => $secteur,
            'contenu' => $secteurs->contenu($secteur),
        ]);
    }
}
