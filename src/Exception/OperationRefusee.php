<?php

namespace App\Exception;

use Symfony\Contracts\Translation\TranslatableInterface;

/** Opération interdite dans l'état actuel, avec un message traduisible (voir MessageTraduisible). */
final class OperationRefusee extends \LogicException implements TranslatableInterface
{
    use MessageTraduisible;
}
