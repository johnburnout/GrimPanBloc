<?php

namespace App\Exception;

use Symfony\Contracts\Translation\TranslatableInterface;

/** Donnée refusée (fichier, saisie…), avec un message traduisible (voir MessageTraduisible). */
final class ArgumentInvalide extends \InvalidArgumentException implements TranslatableInterface
{
    use MessageTraduisible;
}
