<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Locale\Langues;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Langue de l'interface (voir Langues).
 *
 * Avant le LocaleListener de Symfony (priorité 16) et la mise à jour de la langue du
 * traducteur (15). N'interroge pas la base : la finalisation d'une mise à jour
 * passe ici avant les migrations.
 */
final class LangueListener
{
    public function __construct(private readonly Langues $langues)
    {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 17)]
    public function choisir(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $request->setLocale($this->langues->pour($request));
    }

    /** À la connexion, la langue enregistrée dans le compte s'applique sur cet appareil aussi. */
    #[AsEventListener]
    public function connexion(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $reponse = $event->getResponse();
        if ($user instanceof User && $reponse && $this->langues->estActive($user->getLangue())) {
            $reponse->headers->setCookie(self::cookie((string) $user->getLangue(), $event->getRequest()->isSecure()));
        }
    }

    public static function cookie(string $langue, bool $securise): Cookie
    {
        return Cookie::create(Langues::COOKIE, $langue, new \DateTimeImmutable('+1 year'), '/', null, $securise, true, false, Cookie::SAMESITE_LAX);
    }
}
