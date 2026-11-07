<?php

namespace App\Form\Reglages;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

/** Réglage « Toujours utiliser HTTPS » (voir ForcerHttpsSubscriber). */
final class HttpsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('forcer_https', CheckboxType::class, ['required' => false]);
    }

    public function getBlockPrefix(): string
    {
        return 'https';
    }
}
