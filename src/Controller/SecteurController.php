<?php

namespace App\Controller;

use App\Entity\Secteur;
use App\Form\SecteurType;
use App\Repository\SecteurRepository;
use App\Repository\VoieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/secteurs')]
class SecteurController extends AbstractController
{
    #[Route('', name: 'secteur_index')]
    public function index(SecteurRepository $repo): Response
    {
        return $this->render('secteur/index.html.twig', ['secteurs' => $repo->findBy([], ['nom' => 'ASC'])]);
    }

    #[Route('/nouveau', name: 'secteur_new')]
    #[IsGranted('ROLE_USER')] // en plus d'access_control, qui dépend de l'URL
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $secteur = new Secteur();
        $form = $this->createForm(SecteurType::class, $secteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($secteur);
            $em->flush();
            $this->addFlash('success', 'Secteur créé.');

            return $this->redirectToRoute('secteur_show', ['id' => $secteur->getId()]);
        }

        return $this->render('secteur/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'secteur_show', requirements: ['id' => '\d+'])]
    public function show(Secteur $secteur, VoieRepository $voies): Response
    {
        return $this->render('secteur/show.html.twig', [
            'secteur' => $secteur,
            'voies' => $voies->rechercher($secteur),
        ]);
    }
}
