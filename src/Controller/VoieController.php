<?php

namespace App\Controller;

use App\Entity\Secteur;
use App\Entity\User;
use App\Entity\Voie;
use App\Enum\TypePrise;
use App\Form\VoieType;
use App\Security\ConfirmationSuppression;
use App\Security\VoieVoter;
use App\Service\GestionCroix;
use App\Service\GestionVoie;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/voies')]
class VoieController extends AbstractController
{
    public function __construct(
        private readonly GestionVoie $voies,
        private readonly GestionCroix $croix,
    ) {
    }

    #[Route('/nouvelle/{id}', name: 'voie_new', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')] // en plus d'access_control, qui dépend de l'URL
    public function new(Secteur $secteur, Request $request, #[CurrentUser] User $user): Response
    {
        $voie = (new Voie())->setAuteur($user);
        $form = $this->createForm(VoieType::class, $voie, ['secteur' => $secteur]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->voies->enregistrer($voie, $secteur, $user, $form->get('nouvellePhoto')->getData());
            $this->addFlash('success', 'Voie « '.$voie->getNom().' » créée.');

            return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
        }

        return $this->render('voie/new.html.twig', [
            'form' => $form,
            'secteur' => $secteur,
            'typesPrise' => TypePrise::cases(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'voie_edit', requirements: ['id' => '\d+'])]
    #[IsGranted(VoieVoter::MODIFIER, subject: 'voie')]
    public function edit(Voie $voie, Request $request, #[CurrentUser] User $user): Response
    {
        $secteur = $voie->getPhoto()->getSecteur();
        $trace = $this->isGranted(VoieVoter::MODIFIER_TRACE, $voie);

        $form = $this->createForm(VoieType::class, $voie, ['secteur' => $secteur, 'trace' => $trace]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->voies->enregistrer($voie, $secteur, $user, $trace ? $form->get('nouvellePhoto')->getData() : null);
            $this->addFlash('success', 'Voie « '.$voie->getNom().' » modifiée.');

            return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
        }

        return $this->render('voie/edit.html.twig', [
            'form' => $form,
            'voie' => $voie,
            'secteur' => $secteur,
            'trace' => $trace,
            'typesPrise' => TypePrise::cases(),
        ]);
    }

    #[Route('/{id}', name: 'voie_show', requirements: ['id' => '\d+'])]
    public function show(Voie $voie): Response
    {
        return $this->render('voie/show.html.twig', ['voie' => $voie]);
    }

    /**
     * Croix de l'utilisateur connecté (« j'ai réussi cette voie ») :
     * - action « poser » : pose la croix (un validateur propose une cotation) ;
     * - action « modifier » : le validateur change la cotation qu'il propose ;
     * - action « retirer » : la croix est supprimée.
     */
    #[Route('/{id}/croix', name: 'voie_croix', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsCsrfTokenValid(new Expression('"croix" ~ args["voie"].getId()'))]
    public function croix(Voie $voie, Request $request, #[CurrentUser] User $user): Response
    {
        $payload = $request->getPayload();
        $action = $payload->getString('action');
        $existante = null !== $voie->getCroixDe($user);

        if ('retirer' === $action) {
            if ($this->croix->retirer($voie, $user)) {
                $this->addFlash('success', 'Croix retirée.');
            }

            return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
        }

        if (!\in_array($action, ['poser', 'modifier'], true) || ('modifier' === $action && !$existante)) {
            throw $this->createAccessDeniedException('Action inconnue.');
        }

        try {
            $croix = $this->croix->cocher($voie, $user, $payload->getString('cotation'));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
        }

        $this->addFlash('success', $existante
            ? \sprintf('Cotation proposée modifiée : %s.', $croix->getCotation())
            : 'Bravo ! Voie cochée comme réussie.');

        return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'voie_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(VoieVoter::SUPPRIMER, subject: 'voie')]
    #[IsCsrfTokenValid(new Expression('"supprimer" ~ args["voie"].getId()'))]
    public function delete(Voie $voie, Request $request): Response
    {
        // Garde-fou : supprimer une voie validée efface aussi les croix des autres grimpeurs.
        if ($voie->estValidee() && !ConfirmationSuppression::estConfirmee($request->getPayload()->getString('confirmation'))) {
            $this->addFlash('error', ConfirmationSuppression::MESSAGE_ANNULATION);

            return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
        }

        $secteurId = $voie->getPhoto()->getSecteur()->getId();
        $this->voies->supprimer($voie);
        $this->addFlash('success', 'Voie supprimée.');

        return $this->redirectToRoute('secteur_show', ['id' => $secteurId]);
    }
}
