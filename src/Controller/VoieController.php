<?php

namespace App\Controller;

use App\Entity\Croix;
use App\Entity\Prise;
use App\Entity\Secteur;
use App\Entity\User;
use App\Entity\Voie;
use App\Enum\TypePrise;
use App\Form\VoieType;
use App\Security\VoieVoter;
use App\Service\PhotoUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/voies')]
class VoieController extends AbstractController
{
    private const MAX_PRISES = 200;
    public const MOT_CONFIRMATION = 'SUPPRIMER';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PhotoUploader $uploader,
    ) {
    }

    #[Route('/nouvelle/{id}', name: 'voie_new', requirements: ['id' => '\d+'])]
    public function new(Secteur $secteur, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $voie = (new Voie())->setAuteur($user);
        $form = $this->createForm(VoieType::class, $voie, ['secteur' => $secteur]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $this->traiterTrace($form, $voie, $secteur, $user)) {
            $this->em->persist($voie);
            $this->em->flush();
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
    public function edit(Voie $voie, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $secteur = $voie->getPhoto()->getSecteur();
        $trace = $this->isGranted(VoieVoter::MODIFIER_TRACE, $voie);

        $form = $this->createForm(VoieType::class, $voie, ['secteur' => $secteur, 'trace' => $trace]);
        if ($trace) {
            $form->get('prises')->setData(json_encode($voie->getPrisesArray()));
        }
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $ok = $trace ? $this->traiterTrace($form, $voie, $secteur, $user) : $form->isValid();
            if ($ok) {
                $this->em->flush();
                $this->addFlash('success', 'Voie « '.$voie->getNom().' » modifiée.');

                return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
            }
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
     * - action « poser » : un validateur (autre que l'auteur) propose une cotation ;
     * - action « modifier » : le validateur change la cotation qu'il propose ;
     * - action « retirer » : la croix est supprimée.
     * La cotation affichée est recalculée à chaque fois (Voie::recalculerCotation()).
     */
    #[Route('/{id}/croix', name: 'voie_croix', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function croix(Voie $voie, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('croix'.$voie->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $estAuteur = $voie->getAuteur() === $user;
        $existante = $voie->getCroixDe($user);
        $action = $request->getPayload()->getString('action');

        if ('retirer' === $action) {
            if ($existante) {
                $voie->retirerCroix($existante);
                $this->em->remove($existante);
                $this->em->flush();
                $this->addFlash('success', 'Croix retirée.');
            }

            return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
        }

        if (!\in_array($action, ['poser', 'modifier'], true) || ('modifier' === $action && !$existante)) {
            throw $this->createAccessDeniedException('Action inconnue.');
        }

        // L'auteur propose sa cotation sur la voie : sa croix n'en porte pas.
        $cotation = null;
        if (!$estAuteur) {
            $valeur = $request->getPayload()->getString('cotation');
            $cotation = $voie->getSysteme()->cotation($valeur)?->value;
            if (null === $cotation) {
                $this->addFlash('error', 'Choisissez la cotation que vous proposez.');

                return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
            }
        }

        $croix = $existante ?? new Croix($voie, $user);
        $croix->setCotation($cotation);
        $voie->ajouterCroix($croix);
        $this->em->persist($croix);
        $this->em->flush();

        $this->addFlash('success', $existante
            ? sprintf('Cotation proposée modifiée : %s.', $croix->getCotation())
            : 'Bravo ! Voie cochée comme réussie.');

        return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'voie_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Voie $voie, Request $request): Response
    {
        if ($voie->getAuteur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('supprimer'.$voie->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // Garde-fou : supprimer une voie validée efface aussi les croix des autres grimpeurs.
        if ($voie->estValidee() && self::MOT_CONFIRMATION !== trim($request->getPayload()->getString('confirmation'))) {
            $this->addFlash('error', 'Suppression annulée : tapez '.self::MOT_CONFIRMATION.' pour confirmer.');

            return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
        }

        $secteurId = $voie->getPhoto()->getSecteur()->getId();
        $this->em->remove($voie);
        $this->em->flush();
        $this->addFlash('success', 'Voie supprimée.');

        return $this->redirectToRoute('secteur_show', ['id' => $secteurId]);
    }

    /**
     * Valide le formulaire complet (photo, prises, cotation) et applique photo
     * et prises à la voie. Renvoie true si tout est valide.
     */
    private function traiterTrace(FormInterface $form, Voie $voie, Secteur $secteur, User $user): bool
    {
        $fichier = $form->get('nouvellePhoto')->getData();
        if (!$fichier && !$voie->getPhoto()) {
            $form->get('nouvellePhoto')->addError(new FormError('Envoyez une photo ou choisissez-en une existante.'));
        }

        $prises = $this->decoderPrises((string) $form->get('prises')->getData());
        if ([] === $prises) {
            $form->get('prises')->addError(new FormError('Marquez au moins une prise sur la photo.'));
        }

        if (!$form->isValid()) {
            return false;
        }

        if ($fichier) {
            $photo = $this->uploader->upload($fichier, $secteur, $user);
            $this->em->persist($photo);
            $voie->setPhoto($photo);
        }
        $voie->remplacerPrises($prises);

        return true;
    }

    /**
     * Transforme le JSON envoyé par l'éditeur en entités Prise, en ignorant
     * les entrées invalides.
     *
     * @return list<Prise>
     */
    private function decoderPrises(string $json): array
    {
        $donnees = json_decode($json, true);
        if (!\is_array($donnees)) {
            return [];
        }

        $prises = [];
        foreach (\array_slice($donnees, 0, self::MAX_PRISES) as $d) {
            if (!\is_array($d) || !isset($d['x'], $d['y']) || !is_numeric($d['x']) || !is_numeric($d['y'])) {
                continue;
            }
            $prises[] = (new Prise())
                ->setX((float) $d['x'])
                ->setY((float) $d['y'])
                ->setRayon(is_numeric($d['r'] ?? null) ? (float) $d['r'] : 3)
                ->setType(TypePrise::tryFrom((string) ($d['type'] ?? '')) ?? TypePrise::Main);
        }

        return $prises;
    }
}
