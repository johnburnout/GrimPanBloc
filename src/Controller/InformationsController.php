<?php

namespace App\Controller;

use App\Site\InformationsSalle;
use App\Site\ReglementInterieur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/** Page publique « Informations » : adresse, carte, site internet, horaires et caractéristiques de la salle. */
final class InformationsController extends AbstractController
{
    #[Route('/informations', name: 'informations', methods: ['GET'])]
    public function index(InformationsSalle $salle, ReglementInterieur $reglement): Response
    {
        return $this->render('informations/index.html.twig', [
            'infos' => $salle->lire(),
            'position' => $salle->position(),
            'renseigne' => $salle->estRenseigne(),
            'reglement' => $reglement->modifieLe(),
        ]);
    }

    /** Le règlement intérieur, affiché par le lecteur PDF du navigateur. */
    #[Route('/informations/reglement-interieur.pdf', name: 'informations_reglement', methods: ['GET'])]
    public function reglement(ReglementInterieur $reglement): BinaryFileResponse
    {
        $chemin = $reglement->chemin() ?? throw $this->createNotFoundException('Aucun règlement intérieur.');

        $reponse = new BinaryFileResponse($chemin, headers: ['Content-Type' => 'application/pdf'], autoEtag: true, autoLastModified: true);
        $reponse->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'reglement-interieur.pdf');

        return $reponse;
    }
}
