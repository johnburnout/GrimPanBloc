<?php

namespace App\Form\Reglages;

use App\Mail\ReglagesSmtp;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Serveur d'envoi des mails. Données : celles de ReglagesSmtp::lire(), plus le
 * mot de passe (vide = inchangé) et la case « effacer le mot de passe ».
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class SmtpType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $texte = ['required' => false, 'empty_data' => ''];

        $builder
            ->add('hote', TextType::class, $texte + [
                'constraints' => [new Regex('/^[A-Za-z0-9.\-]+$/', message: 'smtp.serveur')],
            ])
            ->add('port', IntegerType::class, [
                'invalid_message' => 'smtp.port',
                'constraints' => [
                    new NotNull(message: 'smtp.port'),
                    new Range(min: 1, max: 65535, notInRangeMessage: 'smtp.port'),
                ],
            ])
            ->add('chiffrement', ChoiceType::class, ['choices' => array_flip(ReglagesSmtp::CHIFFREMENTS)])
            ->add('utilisateur', TextType::class, $texte)
            ->add('mot_de_passe', PasswordType::class, $texte + ['mapped' => false])
            ->add('effacer_mot_de_passe', CheckboxType::class, ['required' => false, 'mapped' => false])
            ->add('expediteur', EmailType::class, $texte + [
                'constraints' => [new Email(message: 'smtp.expediteur')],
            ])
            ->add('expediteur_nom', TextType::class, $texte);
    }

    public function getBlockPrefix(): string
    {
        return 'smtp';
    }
}
