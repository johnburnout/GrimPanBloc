<?php

namespace App\Form\Sauvegardes;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Import d'une archive de sauvegarde téléchargée auparavant.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('archive', FileType::class, [
            'constraints' => [
                new Assert\NotNull(message: 'Choisissez une archive.'),
                new Assert\File(extensions: ['zip'], extensionsMessage: 'Choisissez une archive .zip de sauvegarde GrimPanBloc.'),
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'import';
    }
}
