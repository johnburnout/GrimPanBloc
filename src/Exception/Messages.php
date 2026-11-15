<?php

namespace App\Exception;

use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Message d'une exception, prêt à afficher (message flash) ou à traduire. */
final class Messages
{
    /** Pour un message flash ou un paramètre : traduisible si l'exception l'est, texte brut sinon. */
    public static function de(\Throwable $e): TranslatableMessage|string
    {
        return $e instanceof TranslatableInterface && method_exists($e, 'getParametres')
            ? new TranslatableMessage($e->getMessage(), $e->getParametres())
            : $e->getMessage();
    }

    /** Texte de l'exception dans la langue demandée (par défaut, celle de la requête). */
    public static function texte(\Throwable $e, TranslatorInterface $translator, ?string $locale = null): string
    {
        return $e instanceof TranslatableInterface ? $e->trans($translator, $locale) : $e->getMessage();
    }
}
