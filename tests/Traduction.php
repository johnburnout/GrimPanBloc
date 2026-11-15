<?php

namespace App\Tests;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Traducteur chargé des catalogues du projet, pour les tests sans noyau Symfony. */
final class Traduction
{
    private static ?Translator $translator = null;

    public static function translator(): TranslatorInterface
    {
        if (null === self::$translator) {
            $t = new Translator('fr');
            $t->addLoader('yaml', new YamlFileLoader());
            foreach (glob(\dirname(__DIR__).'/translations/*.yaml') ?: [] as $fichier) {
                // « messages+intl-icu.en.yaml » → domaine « messages+intl-icu », langue « en »
                [$domaine, $langue] = explode('.', basename($fichier, '.yaml'), 2);
                $t->addResource('yaml', $fichier, $langue, $domaine);
            }
            $t->setFallbackLocales(['fr']);
            self::$translator = $t;
        }

        return self::$translator;
    }

    /** Texte français d'un message traduisible (ou d'une chaîne, rendue telle quelle). */
    public static function fr(mixed $message): string
    {
        return $message instanceof \Symfony\Contracts\Translation\TranslatableInterface
            ? $message->trans(self::translator(), 'fr')
            : (string) $message;
    }
}
