<?php

namespace App\Controller;

use App\Site\InformationsSalle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Page publique « Informations » : adresse, carte, site internet, horaires et caractéristiques de la salle. */
final class InformationsController extends AbstractController
{
    #[Route('/informations', name: 'informations', methods: ['GET'])]
    public function index(InformationsSalle $salle): Response
    {
        return $this->render('informations/index.html.twig', [
            'infos' => $salle->lire(),
            'position' => $salle->position(),
            'renseigne' => $salle->estRenseigne(),
        ]);
    }
}
