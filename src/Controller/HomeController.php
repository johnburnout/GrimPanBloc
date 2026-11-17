<?php

namespace App\Controller;

use App\Dto\CriteresVoies;
use App\Entity\User;
use App\Repository\SecteurRepository;
use App\Repository\VoieRepository;
use App\Service\GestionRecalage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class HomeController extends AbstractController
{
    /**
     * Accueil : toutes les voies, en icônes ou en liste, avec filtres et tri. Un grimpeur
     * connecté y voit aussi les nouvelles photos proposées pour ses voies.
     */
    #[Route('/', name: 'app_home')]
    public function index(VoieRepository $voies, SecteurRepository $secteurs, GestionRecalage $recalages, #[CurrentUser] ?User $user = null, #[MapQueryString] CriteresVoies $criteres = new CriteresVoies()): Response
    {
        return $this->render('home/index.html.twig', [
            'voies' => $voies->rechercher($criteres),
            'criteres' => $criteres,
            'secteurs' => $secteurs->findBy([], ['nom' => 'ASC']),
            'propositionsPhoto' => null !== $user ? $recalages->propositionsPour($user) : [],
        ]);
    }
}
