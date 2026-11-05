<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** Profil : email et mot de passe. Le pseudo n'est pas modifiable. */
class ProfilType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'Email', 'attr' => ['autocomplete' => 'email']])
            ->add('nouveauMotDePasse', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'first_options' => ['label' => 'Nouveau mot de passe', 'help' => 'Laissez vide pour garder le mot de passe actuel.', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Confirmation du nouveau mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'constraints' => [new Length(min: 8, max: 4096, minMessage: 'Au moins {{ limit }} caractères.')],
            ])
            ->add('motDePasseActuel', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'help' => 'Obligatoire pour enregistrer une modification.',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new NotBlank(message: 'Saisissez votre mot de passe actuel.'),
                    new UserPassword(message: 'Mot de passe actuel incorrect.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
