<?php

namespace App\Exception;

use Symfony\Contracts\Translation\TranslatableInterface;

/** Opération impossible (disque, réseau, serveur…), avec un message traduisible (voir MessageTraduisible). */
final class EchecOperation extends \RuntimeException implements TranslatableInterface
{
    use MessageTraduisible;
}
