<?php

namespace App\Form\Reglages;

use App\Service\PhotoOptimiseur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Nom et logo de la salle ou du club (voir IdentiteClub).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ClubType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom_club', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Length(max: 100)],
            ])
            ->add('supprimer_logo', CheckboxType::class, ['required' => false])
            ->add('logo', FileType::class, [
                'required' => false,
                'constraints' => [new Image(
                    maxSize: '5M',
                    mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
                    maxSizeMessage: 'logo.taille',
                    mimeTypesMessage: 'logo.format',
                    maxPixels: PhotoOptimiseur::PIXELS_MAX,
                    maxPixelsMessage: 'logo.pixels',
                )],
            ]);
    }

    public function getBlockPrefix(): string
    {
        return 'club';
    }
}
