<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfilType;
use App\Repository\CroixRepository;
use App\Repository\VoieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Espace personnel : profil, croix posées, voies créées. */
#[Route('/profil')]
#[IsGranted('ROLE_USER')]
class ProfilController extends AbstractController
{
    #[Route('', name: 'profil', methods: ['GET', 'POST'])]
    public function profil(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ProfilType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $nouveau = (string) $form->get('nouveauMotDePasse')->getData();
                if ('' !== $nouveau) {
                    $user->setPassword($hasher->hashPassword($user, $nouveau));
                }
                $em->flush();
                $this->addFlash('success', 'Profil enregistré.');

                return $this->redirectToRoute('profil');
            }
            // Formulaire invalide : on annule les modifications faites sur le compte
            // (sinon l'email refusé serait conservé dans la session de connexion).
            $em->refresh($user);
        }

        return $this->render('profil/index.html.twig', ['form' => $form]);
    }

    #[Route('/croix', name: 'profil_croix', methods: ['GET'])]
    public function croix(CroixRepository $croix): Response
    {
        return $this->render('profil/croix.html.twig', [
            'croix' => $croix->findBy(['grimpeur' => $this->getUser()], ['creeLe' => 'DESC']),
        ]);
    }

    #[Route('/voies', name: 'profil_voies', methods: ['GET'])]
    public function voies(VoieRepository $voies): Response
    {
        return $this->render('profil/voies.html.twig', [
            'voies' => $voies->findBy(['auteur' => $this->getUser()], ['creeLe' => 'DESC']),
        ]);
    }
}
