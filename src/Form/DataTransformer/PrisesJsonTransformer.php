<?php

namespace App\Form\DataTransformer;

use App\Entity\Prise;
use App\Enum\TypePrise;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * Champ caché « prises » du formulaire de voie : les prises dessinées par
 * l'éditeur JS circulent en JSON ([{x, y, r, type}, …]).
 *
 * Les entrées invalides sont ignorées (jamais d'erreur de transformation) : le
 * formulaire signale seulement l'absence de prise, via une contrainte Count.
 *
 * @implements DataTransformerInterface<iterable<Prise>, string>
 */
final class PrisesJsonTransformer implements DataTransformerInterface
{
    public const MAX_PRISES = 200;
    private const RAYON_DEFAUT = 3;

    public function transform(mixed $value): string
    {
        $prises = [];
        foreach ($value ?? [] as $prise) {
            $prises[] = $prise->toArray();
        }

        return json_encode($prises, JSON_THROW_ON_ERROR);
    }

    /** @return list<Prise> */
    public function reverseTransform(mixed $value): array
    {
        $donnees = json_decode((string) $value, true);
        if (!\is_array($donnees)) {
            return [];
        }

        $prises = [];
        foreach (\array_slice($donnees, 0, self::MAX_PRISES) as $d) {
            if (!\is_array($d) || !isset($d['x'], $d['y']) || !is_numeric($d['x']) || !is_numeric($d['y'])) {
                continue;
            }
            $prises[] = (new Prise())
                ->setX((float) $d['x'])
                ->setY((float) $d['y'])
                ->setRayon(is_numeric($d['r'] ?? null) ? (float) $d['r'] : self::RAYON_DEFAUT)
                ->setType(TypePrise::tryFrom((string) ($d['type'] ?? '')) ?? TypePrise::Main);
        }

        return $prises;
    }
}
