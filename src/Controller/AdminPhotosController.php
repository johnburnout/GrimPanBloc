<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Repository\PhotoRepository;
use App\Service\GestionPhotos;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Photos qu'aucune voie n'utilise : elles restent dans leur secteur pour être
 * réutilisées, et l'administrateur peut les supprimer d'ici. Liste paginée.
 */
#[Route('/admin/photos')]
#[IsGranted('ROLE_ADMIN')]
class AdminPhotosController extends AbstractController
{
    public const PAR_PAGE = 30;

    #[Route('', name: 'admin_photos', methods: ['GET'])]
    public function index(PhotoRepository $photos, #[MapQueryParameter] int $page = 1): Response
    {
        $total = $photos->compterNonUtilisees();
        $pages = max(1, (int) ceil($total / self::PAR_PAGE));
        // Page hors limites (dernière photo d'une page supprimée, lien ancien) : la plus proche
        $page = min(max(1, $page), $pages);

        return $this->render('admin/photos.html.twig', [
            'photos' => $photos->nonUtilisees(($page - 1) * self::PAR_PAGE, self::PAR_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_photo_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsCsrfTokenValid(new Expression('"photo" ~ args["photo"].getId()'))]
    public function supprimer(Photo $photo, GestionPhotos $gestion, #[MapQueryParameter] int $page = 1): Response
    {
        try {
            $gestion->supprimer($photo);
            $this->addFlash('success', sprintf('Photo du secteur « %s » supprimée.', $photo->getSecteur()->getNom()));
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        // Retour à la page où l'on était
        return $this->redirectToRoute('admin_photos', $page > 1 ? ['page' => $page] : []);
    }
}
