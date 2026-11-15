<?php

namespace App\Controller;

use App\Dto\CriteresVoies;
use App\Repository\SecteurRepository;
use App\Repository\VoieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    /**
     * Accueil : toutes les voies, en icônes ou en liste, avec filtres et tri.
     */
    #[Route('/', name: 'app_home')]
    public function index(VoieRepository $voies, SecteurRepository $secteurs, #[MapQueryString] CriteresVoies $criteres = new CriteresVoies()): Response
    {
        return $this->render('home/index.html.twig', [
            'voies' => $voies->rechercher($criteres),
            'criteres' => $criteres,
            'secteurs' => $secteurs->findBy([], ['nom' => 'ASC']),
        ]);
    }
}
