<?php

namespace App\Controller;

use App\Enum\CotationCouleur;
use App\Enum\CotationFr;
use App\Enum\SystemeCotation;
use App\Repository\SecteurRepository;
use App\Repository\VoieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    /**
     * Accueil : liste des voies avec filtres (secteur, échelle, difficulté).
     */
    #[Route('/', name: 'app_home')]
    public function index(Request $request, VoieRepository $voies, SecteurRepository $secteurs): Response
    {
        $secteur = $request->query->getInt('secteur') ? $secteurs->find($request->query->getInt('secteur')) : null;
        $systeme = SystemeCotation::tryFrom($request->query->getString('systeme'));

        $enum = SystemeCotation::Couleur === $systeme ? CotationCouleur::class : CotationFr::class;
        $min = $enum::tryFrom($request->query->getString('min'));
        $max = $enum::tryFrom($request->query->getString('max'));

        return $this->render('home/index.html.twig', [
            'voies' => $voies->rechercher($secteur, $systeme, $min?->rang(), $max?->rang()),
            'secteurs' => $secteurs->findBy([], ['nom' => 'ASC']),
            'filtre' => [
                'secteur' => $secteur,
                'systeme' => $systeme,
                'min' => $min,
                'max' => $max,
            ],
            'cotationsFr' => CotationFr::cases(),
            'cotationsCouleur' => CotationCouleur::cases(),
        ]);
    }
}
