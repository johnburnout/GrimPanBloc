<?php

namespace App\Form;

use App\Entity\Photo;
use App\Entity\Secteur;
use App\Form\DataTransformer\PrisesJsonTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Changement de photo d'une voie : une autre photo du secteur ou une photo envoyée, et
 * les prises replacées dessus. Données : ['photo' => ?Photo, 'nouvellePhoto' => ?UploadedFile,
 * 'prises' => list<Prise>]. Les prises ne servent qu'à reprendre la position et la
 * taille des prises de la voie (voir GestionRecalage::changerPhoto()).
 *
 * Préfixe « voie » : l'éditeur (public/js/editeur.js) attend les champs #voie_*.
 *
 * @extends AbstractType<array<string, mixed>>
 */
class ChangementPhotoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        VoieType::ajouterChoixPhoto($builder, $options['secteur']);
        $builder->add('prises', HiddenType::class, ['attr' => ['data-prises' => '']]);
        $builder->get('prises')->addModelTransformer(new PrisesJsonTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'constraints' => [new Callback(self::exigerUneAutrePhoto(...))],
        ]);
        $resolver->setRequired(['secteur', 'actuelle']);
        $resolver->setAllowedTypes('secteur', Secteur::class);
        $resolver->setAllowedTypes('actuelle', Photo::class);
    }

    public function getBlockPrefix(): string
    {
        return 'voie';
    }

    /**
     * Une photo envoyée, ou une photo existante autre que celle de la voie.
     *
     * @param array<string, mixed> $donnees
     */
    private static function exigerUneAutrePhoto(array $donnees, ExecutionContextInterface $context): void
    {
        if (null !== ($donnees['nouvellePhoto'] ?? null)) {
            return;
        }
        $photo = $donnees['photo'] ?? null;
        if (null === $photo) {
            $context->addViolation('voie.photo.requise');
        } elseif ($photo === $context->getRoot()->getConfig()->getOption('actuelle')) {
            $context->addViolation('voie.photo.identique');
        }
    }
}
