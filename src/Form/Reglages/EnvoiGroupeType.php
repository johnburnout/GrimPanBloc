<?php

namespace App\Form\Reglages;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Message envoyé par mail à tous les utilisateurs actifs, ou aux seuls administrateurs.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class EnvoiGroupeType extends AbstractType
{
    public const LONGUEUR_MAX_OBJET = 200;
    public const LONGUEUR_MAX_CORPS = 20000;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cible', ChoiceType::class, [
                'choices' => ['tous' => 'tous', 'admins' => 'admins'],
                'expanded' => true,
            ])
            ->add('objet', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'groupe.objet.requis'),
                    new Length(max: self::LONGUEUR_MAX_OBJET, maxMessage: 'groupe.objet.long'),
                ],
            ])
            ->add('corps', TextareaType::class, [
                'constraints' => [
                    new NotBlank(message: 'groupe.message.requis'),
                    new Length(max: self::LONGUEUR_MAX_CORPS, maxMessage: 'groupe.message.long'),
                ],
            ]);
    }

    public function getBlockPrefix(): string
    {
        return 'envoi_groupe';
    }
}
