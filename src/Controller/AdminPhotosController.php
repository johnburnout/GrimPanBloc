<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Repository\PhotoRepository;
use App\Service\GestionPhotos;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Photos qu'aucune voie n'utilise : elles restent dans leur secteur pour être
 * réutilisées, et l'administrateur peut les supprimer d'ici.
 */
#[Route('/admin/photos')]
#[IsGranted('ROLE_ADMIN')]
class AdminPhotosController extends AbstractController
{
    #[Route('', name: 'admin_photos', methods: ['GET'])]
    public function index(PhotoRepository $photos): Response
    {
        return $this->render('admin/photos.html.twig', ['photos' => $photos->nonUtilisees()]);
    }

    #[Route('/{id}/supprimer', name: 'admin_photo_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsCsrfTokenValid(new Expression('"photo" ~ args["photo"].getId()'))]
    public function supprimer(Photo $photo, GestionPhotos $gestion): Response
    {
        try {
            $gestion->supprimer($photo);
            $this->addFlash('success', sprintf('Photo du secteur « %s » supprimée.', $photo->getSecteur()->getNom()));
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_photos');
    }
}
