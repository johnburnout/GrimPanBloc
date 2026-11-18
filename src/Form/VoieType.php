<?php

namespace App\Form;

use App\Entity\Photo;
use App\Entity\Secteur;
use App\Entity\Voie;
use App\Enum\CotationCouleur;
use App\Enum\CotationFr;
use App\Enum\SystemeCotation;
use App\Form\DataTransformer\PrisesJsonTransformer;
use App\Service\PhotoOptimiseur;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** @extends AbstractType<Voie> */
class VoieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Secteur $secteur */
        $secteur = $options['secteur'];

        $builder->add('nom', TextType::class, ['label' => 'voie.form.nom']);

        // Voie validée : seuls le nom et la description restent modifiables.
        if (!$options['trace']) {
            $builder->add('description', TextareaType::class, ['label' => 'voie.form.description', 'required' => false]);

            return;
        }

        // Modification : la photo change par sa propre page (ChangementPhotoType), sans perdre de prise
        if ($options['choix_photo']) {
            self::ajouterChoixPhoto($builder, $secteur, ['mapped' => false, 'constraints' => [new Callback(self::exigerUnePhoto(...))]]);
        }

        $builder
            // Les prises sont dessinées en JS et envoyées en JSON dans ce champ caché.
            ->add('prises', HiddenType::class, [
                'attr' => ['data-prises' => ''],
                'getter' => fn (Voie $v) => $v->getPrises()->toArray(),
                'setter' => fn (Voie $v, array $prises) => $v->remplacerPrises($prises),
                'constraints' => [new Count(min: 1, minMessage: 'voie.prises.min')],
            ])
            // L'ordre compte : le système doit être défini avant la cotation.
            ->add('systeme', EnumType::class, [
                'class' => SystemeCotation::class,
                'label' => 'voie.form.echelle',
                'expanded' => true,
                'choice_label' => fn (SystemeCotation $s) => $s,
            ])
            ->add('cotationFr', EnumType::class, [
                'class' => CotationFr::class,
                'label' => 'voie.cotation',
                'required' => false,
                'placeholder' => '—',
                'choice_label' => fn (CotationFr $c) => $c->libelle(),
                'choice_translation_domain' => false,
            ])
            ->add('cotationCouleur', EnumType::class, [
                'class' => CotationCouleur::class,
                'label' => 'voie.form.couleur',
                'required' => false,
                'placeholder' => '—',
                'choice_label' => fn (CotationCouleur $c) => $c,
            ])
            // Pieds libres : les prises de pied restent enregistrées mais ne s'affichent pas
            ->add('piedsLibres', ChoiceType::class, [
                'label' => 'voie.form.pieds',
                'expanded' => true,
                'choices' => ['voie.form.pieds_imposes' => false, 'voie.form.pieds_libres' => true],
            ])
            ->add('description', TextareaType::class, ['label' => 'voie.form.description', 'required' => false]);

        $builder->get('prises')->addModelTransformer(new PrisesJsonTransformer());
    }

    /**
     * Champs « photo » (existante du secteur) et « nouvellePhoto » (envoyée), partagés
     * avec le changement de photo (ChangementPhotoType).
     *
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed> $optionsNouvelle options en plus pour « nouvellePhoto »
     */
    public static function ajouterChoixPhoto(FormBuilderInterface $builder, Secteur $secteur, array $optionsNouvelle = []): void
    {
        $contraintes = [self::contrainteImage(), ...($optionsNouvelle['constraints'] ?? [])];
        $builder
            ->add('photo', EntityType::class, [
                'class' => Photo::class,
                'label' => 'voie.form.photo_existante',
                'required' => false,
                'placeholder' => 'voie.form.envoyer_nouvelle',
                'query_builder' => fn (EntityRepository $r) => $r->createQueryBuilder('p')
                    ->andWhere('p.secteur = :s')->setParameter('s', $secteur)
                    ->orderBy('p.creeLe', 'DESC'),
                'choice_label' => fn (Photo $p) => new TranslatableMessage('voie.form.photo_choix', ['id' => $p->getId(), 'date' => $p->getCreeLe()->format('d/m/Y')]),
                'choice_translation_domain' => 'messages',
                'choice_attr' => fn (Photo $p) => ['data-src' => '/'.$p->getChemin()],
            ])
            ->add('nouvellePhoto', FileType::class, [
                'label' => 'voie.form.nouvelle_photo',
                'required' => false,
                // Pas d'attribut « capture » : il imposerait l'appareil photo sur téléphone,
                // alors qu'on doit pouvoir aussi choisir une photo de la photothèque.
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                'constraints' => $contraintes,
            ] + $optionsNouvelle);
    }

    /** Formats et taille acceptés pour une photo envoyée (voie ou secteur, voir PhotosSecteurType). */
    public static function contrainteImage(): Image
    {
        return new Image(maxSize: '15M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
            mimeTypesMessage: 'voie.photo.format',
            maxPixels: PhotoOptimiseur::PIXELS_MAX,
            maxPixelsMessage: 'voie.photo.trop_grande');
    }

    /** Sans nouvelle photo, il faut en avoir choisi une existante. */
    private static function exigerUnePhoto(?UploadedFile $fichier, ExecutionContextInterface $context): void
    {
        $voie = $context->getRoot()->getData();
        if (null === $fichier && null === $voie?->getPhoto()) {
            $context->addViolation('voie.photo.requise');
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Voie::class,
            // false : formulaire réduit au nom et à la description (voie validée)
            'trace' => true,
            // false (modification) : pas de choix de photo, voir ChangementPhotoType
            'choix_photo' => true,
        ]);
        $resolver->setAllowedTypes('choix_photo', 'bool');
        $resolver->setAllowedTypes('trace', 'bool');
        $resolver->setRequired('secteur');
        $resolver->setAllowedTypes('secteur', Secteur::class);
    }
}
