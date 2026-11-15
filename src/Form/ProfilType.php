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

/**
 * Profil : email et mot de passe. Le pseudo n'est pas modifiable.
 *
 * @extends AbstractType<User>
 */
class ProfilType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'commun.email', 'attr' => ['autocomplete' => 'email']])
            ->add('nouveauMotDePasse', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'invalid_message' => 'mot_de_passe.differents',
                'first_options' => ['label' => 'profil.nouveau_mot_de_passe', 'help' => 'profil.nouveau_mot_de_passe_aide', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'profil.confirmation_mot_de_passe', 'attr' => ['autocomplete' => 'new-password']],
                'constraints' => [new Length(min: 8, max: 4096, minMessage: 'mot_de_passe.court')],
            ])
            ->add('motDePasseActuel', PasswordType::class, [
                'label' => 'profil.mot_de_passe_actuel',
                'help' => 'profil.mot_de_passe_actuel_aide',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new NotBlank(message: 'mot_de_passe.actuel_requis'),
                    new UserPassword(message: 'mot_de_passe.actuel_incorrect'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
