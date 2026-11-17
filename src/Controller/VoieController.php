<?php

namespace App\Controller;

use App\Entity\Recalage;
use App\Entity\Secteur;
use App\Entity\User;
use App\Entity\Voie;
use App\Enum\TypePrise;
use App\Exception\Messages;
use App\Form\ChangementPhotoType;
use App\Form\VoieType;
use App\Repository\RecalageRepository;
use App\Security\ConfirmationSuppression;
use App\Security\VoieVoter;
use App\Service\GestionCroix;
use App\Service\GestionRecalage;
use App\Service\GestionVoie;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[Route('/voies')]
class VoieController extends AbstractController
{
    public function __construct(
        private readonly GestionVoie $voies,
        private readonly GestionCroix $croix,
        private readonly GestionRecalage $recalages,
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
            $this->addFlash('success', new TranslatableMessage('flash.voie.creee', ['nom' => $voie->getNom()]));

            return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
        }

        return $this->render('voie/new.html.twig', [
            'form' => $form,
            'secteur' => $secteur,
            'typesPrise' => TypePrise::cases(),
        ]);
    }

    /**
     * Nouvelle voie pré-remplie à partir d'une autre (même photo, mêmes prises…),
     * à ajuster avant de l'enregistrer : rien n'est créé avant la validation.
     */
    #[Route('/{id}/dupliquer', name: 'voie_dupliquer', requirements: ['id' => '\d+'])]
    #[IsGranted(VoieVoter::DUPLIQUER, subject: 'voie')]
    public function dupliquer(Voie $voie, Request $request, #[CurrentUser] User $user): Response
    {
        $secteur = $voie->getPhoto()->getSecteur();
        $copie = $voie->dupliquerPour($user);
        $form = $this->createForm(VoieType::class, $copie, ['secteur' => $secteur]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->voies->enregistrer($copie, $secteur, $user, $form->get('nouvellePhoto')->getData());
            $this->addFlash('success', new TranslatableMessage('flash.voie.copiee', ['nom' => $copie->getNom(), 'source' => $voie->getNom()]));

            return $this->redirectToRoute('voie_show', ['id' => $copie->getId()]);
        }

        return $this->render('voie/new.html.twig', [
            'form' => $form,
            'secteur' => $secteur,
            'source' => $voie,
            'typesPrise' => TypePrise::cases(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'voie_edit', requirements: ['id' => '\d+'])]
    #[IsGranted(VoieVoter::MODIFIER, subject: 'voie')]
    public function edit(Voie $voie, Request $request, #[CurrentUser] User $user): Response
    {
        $secteur = $voie->getPhoto()->getSecteur();
        $trace = $this->isGranted(VoieVoter::MODIFIER_TRACE, $voie);

        // Pas de choix de photo ici : elle change par voie_changer_photo, sans perdre de prise
        $form = $this->createForm(VoieType::class, $voie, ['secteur' => $secteur, 'trace' => $trace, 'choix_photo' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->voies->enregistrer($voie, $secteur, $user);
            $this->addFlash('success', new TranslatableMessage('flash.voie.modifiee', ['nom' => $voie->getNom()]));

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

    /**
     * Changement de photo, pour toute voie (validée ou non) : une autre photo du secteur
     * ou une photo envoyée, puis les prises replacées. Changer de photo ne change pas la
     * voie : on ne peut ni ajouter, ni supprimer de prise, ni changer leur type ; seules
     * leur position et leur taille se réajustent. Les déplacements sont enregistrés
     * (Recalage) et la nouvelle photo est proposée aux autres voies de l'ancienne.
     *
     * Avec « ?proposition={recalage} » (lien de l'accueil), la nouvelle photo proposée
     * est choisie d'office et les prises y sont replacées d'après le recalage.
     */
    #[Route('/{id}/photo', name: 'voie_changer_photo', requirements: ['id' => '\d+'])]
    #[IsGranted(VoieVoter::CHANGER_PHOTO, subject: 'voie')]
    public function changerPhoto(Voie $voie, Request $request, #[CurrentUser] User $user, RecalageRepository $recalages): Response
    {
        $actuelle = $voie->getPhoto();
        $proposition = $recalages->find($request->query->getInt('proposition'));
        if (null !== $proposition && !$this->recalages->estProposable($voie, $proposition)) {
            $proposition = null;
        }
        $donnees = null !== $proposition
            ? ['photo' => $proposition->getPhotoCible(), 'prises' => $this->recalages->prisesProposees($voie, $proposition)]
            : ['photo' => $actuelle, 'prises' => $voie->getPrises()->toArray()];
        $prisesParPhoto = $this->recalages->prisesParPhoto($voie);

        $form = $this->createForm(ChangementPhotoType::class, $donnees, ['secteur' => $actuelle->getSecteur(), 'actuelle' => $actuelle]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $cible = $form->get('nouvellePhoto')->getData() ?? $form->get('photo')->getData();
            $recalage = $this->recalages->changerPhoto($voie, $cible, $user, $form->get('prises')->getData() ?? []);
            $this->addFlash('success', new TranslatableMessage('flash.voie.photo_adoptee', ['nom' => $voie->getNom()]));
            $autres = null !== $recalage ? \count($this->recalages->voiesConcernees($recalage, $voie)) : 0;
            if ($autres > 0) {
                $this->addFlash('info', new TranslatableMessage('flash.voie.photo_proposee', ['nombre' => $autres]));
            }

            return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
        }

        return $this->render('voie/changer_photo.html.twig', [
            'form' => $form,
            'voie' => $voie,
            'proposition' => $proposition,
            'prisesParPhoto' => $prisesParPhoto,
        ]);
    }

    /**
     * Nouvelle photo proposée pour la voie (alerte de l'accueil) : la voie sur sa photo
     * actuelle et telle qu'elle serait sur la nouvelle, prises replacées d'après le recalage.
     */
    #[Route('/{id}/nouvelle-photo/{recalage}', name: 'voie_proposition_photo', requirements: ['id' => '\d+', 'recalage' => '\d+'], methods: ['GET'])]
    #[IsGranted(VoieVoter::CHANGER_PHOTO, subject: 'voie')]
    public function propositionPhoto(Voie $voie, #[MapEntity(id: 'recalage')] Recalage $recalage): Response
    {
        if (!$this->recalages->estProposable($voie, $recalage)) {
            throw $this->createNotFoundException('Aucune nouvelle photo proposée pour cette voie.');
        }

        return $this->render('voie/proposition_photo.html.twig', [
            'voie' => $voie,
            'recalage' => $recalage,
            'prisesProposees' => $voie->filtrerAffichees($this->recalages->prisesProposees($voie, $recalage)),
        ]);
    }

    /** Le créateur garde sa photo : la proposition disparaît de l'accueil. */
    #[Route('/{id}/nouvelle-photo/{recalage}/ignorer', name: 'voie_ignorer_photo', requirements: ['id' => '\d+', 'recalage' => '\d+'], methods: ['POST'])]
    #[IsGranted(VoieVoter::CHANGER_PHOTO, subject: 'voie')]
    #[IsCsrfTokenValid(new Expression('"ignorer" ~ args["voie"].getId()'))]
    public function ignorerPhoto(Voie $voie, #[MapEntity(id: 'recalage')] Recalage $recalage): Response
    {
        if ($this->recalages->estProposable($voie, $recalage)) {
            $this->recalages->ignorer($voie, $recalage);
            $this->addFlash('success', new TranslatableMessage('flash.voie.photo_ignoree', ['nom' => $voie->getNom()]));
        }

        return $this->redirectToRoute('app_home');
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
                $this->addFlash('success', new TranslatableMessage('flash.croix.retiree'));
            }

            return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
        }

        if (!\in_array($action, ['poser', 'modifier'], true) || ('modifier' === $action && !$existante)) {
            throw $this->createAccessDeniedException('Action inconnue.');
        }

        try {
            $croix = $this->croix->cocher($voie, $user, $payload->getString('cotation'));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', Messages::de($e));

            return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
        }

        $this->addFlash('success', $existante
            ? new TranslatableMessage('flash.croix.cotation_modifiee', ['cotation' => $voie->getSysteme()->cotation($croix->getCotation()) ?? (string) $croix->getCotation()])
            : new TranslatableMessage('flash.croix.posee'));

        return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'voie_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(VoieVoter::SUPPRIMER, subject: 'voie')]
    #[IsCsrfTokenValid(new Expression('"supprimer" ~ args["voie"].getId()'))]
    public function delete(Voie $voie, Request $request, ConfirmationSuppression $confirmation): Response
    {
        // Garde-fou : supprimer une voie validée efface aussi les croix des autres grimpeurs.
        if ($voie->estValidee() && !$confirmation->estConfirmee($request->getPayload()->getString('confirmation'))) {
            $this->addFlash('error', ConfirmationSuppression::annulation());

            return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
        }

        $secteurId = $voie->getPhoto()->getSecteur()->getId();
        $this->voies->supprimer($voie);
        $this->addFlash('success', new TranslatableMessage('flash.voie.supprimee'));

        return $this->redirectToRoute('secteur_show', ['id' => $secteurId]);
    }
}
