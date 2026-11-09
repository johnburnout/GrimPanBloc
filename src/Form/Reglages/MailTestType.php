<?php

namespace App\Form\Reglages;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Envoi d'un mail générique pour vérifier les réglages SMTP.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class MailTestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('destinataire', EmailType::class, [
            'constraints' => [
                new NotBlank(message: 'Adresse du destinataire invalide.'),
                new Email(message: 'Adresse du destinataire invalide.'),
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'mail_test';
    }
}
