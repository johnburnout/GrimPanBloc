<?php

namespace App\Form\Sauvegardes;

use App\Sauvegarde\ReglageSauvegardes;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Nombre de sauvegardes automatiques conservées (voir ReglageSauvegardes).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ConservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('automatiques', IntegerType::class, [
            'attr' => ['min' => ReglageSauvegardes::MIN, 'max' => ReglageSauvegardes::MAX],
            'constraints' => [
                new Assert\NotNull(),
                new Assert\Range(min: ReglageSauvegardes::MIN, max: ReglageSauvegardes::MAX),
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'conservation';
    }
}
