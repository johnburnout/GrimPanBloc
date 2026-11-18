<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;

/**
 * Envoi de photos dans un secteur par un administrateur, depuis la page du secteur.
 * Elles y restent disponibles pour les nouvelles voies. Données : ['photos' => list<UploadedFile>].
 *
 * @extends AbstractType<array<string, mixed>>
 */
class PhotosSecteurType extends AbstractType
{
    /** Au plus autant de fichiers par envoi (PHP en accepte 20 par défaut : max_file_uploads). */
    public const MAX = 20;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('photos', FileType::class, [
            'label' => 'secteurs.photos.fichiers',
            'multiple' => true,
            // Pas d'attribut « capture » : on doit pouvoir choisir dans la photothèque
            'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
            'constraints' => [
                new Count(min: 1, max: self::MAX, minMessage: 'secteur.photos.requises', maxMessage: 'secteur.photos.trop_nombreuses'),
                new All([VoieType::contrainteImage()]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
