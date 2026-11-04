<?php

namespace App\Controller;

use App\Entity\Prise;
use App\Entity\Secteur;
use App\Entity\User;
use App\Entity\Voie;
use App\Enum\TypePrise;
use App\Form\VoieType;
use App\Service\PhotoUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/voies')]
class VoieController extends AbstractController
{
    private const MAX_PRISES = 200;

    #[Route('/nouvelle/{id}', name: 'voie_new', requirements: ['id' => '\d+'])]
    public function new(Secteur $secteur, Request $request, EntityManagerInterface $em, PhotoUploader $uploader): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $voie = (new Voie())->setAuteur($user);
        $form = $this->createForm(VoieType::class, $voie, ['secteur' => $secteur]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $fichier = $form->get('nouvellePhoto')->getData();
            if (!$fichier && !$voie->getPhoto()) {
                $form->get('nouvellePhoto')->addError(new FormError('Envoyez une photo ou choisissez-en une existante.'));
            }

            $prises = $this->decoderPrises((string) $form->get('prises')->getData());
            if ([] === $prises) {
                $form->get('prises')->addError(new FormError('Marquez au moins une prise sur la photo.'));
            }

            if ($form->isValid()) {
                if ($fichier) {
                    $photo = $uploader->upload($fichier, $secteur, $user);
                    $em->persist($photo);
                    $voie->setPhoto($photo);
                }
                foreach ($prises as $prise) {
                    $voie->addPrise($prise);
                }
                $em->persist($voie);
                $em->flush();

                $this->addFlash('success', 'Voie « '.$voie->getNom().' » créée.');

                return $this->redirectToRoute('voie_show', ['id' => $voie->getId()]);
            }
        }

        return $this->render('voie/new.html.twig', [
            'form' => $form,
            'secteur' => $secteur,
            'typesPrise' => TypePrise::cases(),
        ]);
    }

    #[Route('/{id}', name: 'voie_show', requirements: ['id' => '\d+'])]
    public function show(Voie $voie): Response
    {
        return $this->render('voie/show.html.twig', ['voie' => $voie]);
    }

    #[Route('/{id}/supprimer', name: 'voie_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Voie $voie, Request $request, EntityManagerInterface $em): Response
    {
        if ($voie->getAuteur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('supprimer'.$voie->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $secteurId = $voie->getPhoto()->getSecteur()->getId();
        $em->remove($voie);
        $em->flush();
        $this->addFlash('success', 'Voie supprimée.');

        return $this->redirectToRoute('secteur_show', ['id' => $secteurId]);
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
