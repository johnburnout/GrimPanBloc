<?php

namespace App\Controller;

use App\Entity\User;
use App\EventSubscriber\LangueListener;
use App\Locale\Langues;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/** Menu des langues du pied de page : mémorisé dans un cookie et, pour un grimpeur connecté, dans son compte. */
final class LangueController extends AbstractController
{
    /** Formulaire GET du pied de page : /langue?langue=en&retour=/secteurs */
    #[Route('/langue', name: 'langue', methods: ['GET'])]
    public function choisir(Request $request, Langues $langues, EntityManagerInterface $em, #[MapQueryParameter] string $langue = '', #[MapQueryParameter] string $retour = '/'): RedirectResponse
    {
        if (!$langues->estActive($langue)) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setLangue($langue);
            $em->flush();
        }

        // Retour sur la page d'origine, jamais vers un autre site
        $local = str_starts_with($retour, '/') && !str_starts_with($retour, '//') && !str_starts_with($retour, '/\\');
        $reponse = $this->redirect($local ? $retour : $this->generateUrl('app_home'));
        $reponse->headers->setCookie(LangueListener::cookie($langue, $request->isSecure()));

        return $reponse;
    }
}
