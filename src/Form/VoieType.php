<?php

namespace App\Form;

use App\Entity\Photo;
use App\Entity\Secteur;
use App\Entity\Voie;
use App\Enum\CotationCouleur;
use App\Enum\CotationFr;
use App\Enum\SystemeCotation;
use App\Form\DataTransformer\PrisesJsonTransformer;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class VoieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Secteur $secteur */
        $secteur = $options['secteur'];

        $builder->add('nom', TextType::class, ['label' => 'Nom de la voie']);

        // Voie validée : seuls le nom et la description restent modifiables.
        if (!$options['trace']) {
            $builder->add('description', TextareaType::class, ['label' => 'Description / consignes', 'required' => false]);

            return;
        }

        $builder
            ->add('photo', EntityType::class, [
                'class' => Photo::class,
                'label' => 'Photo existante du secteur',
                'required' => false,
                'placeholder' => '— Envoyer une nouvelle photo —',
                'query_builder' => fn (EntityRepository $r) => $r->createQueryBuilder('p')
                    ->andWhere('p.secteur = :s')->setParameter('s', $secteur)
                    ->orderBy('p.creeLe', 'DESC'),
                'choice_attr' => fn (Photo $p) => ['data-src' => '/'.$p->getChemin()],
            ])
            ->add('nouvellePhoto', FileType::class, [
                'label' => 'Nouvelle photo',
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp', 'capture' => 'environment'],
                'constraints' => [
                    new Image(maxSize: '15M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP.'),
                    new Callback(self::exigerUnePhoto(...)),
                ],
            ])
            // Les prises sont dessinées en JS et envoyées en JSON dans ce champ caché.
            ->add('prises', HiddenType::class, [
                'attr' => ['data-prises' => ''],
                'getter' => fn (Voie $v) => $v->getPrises()->toArray(),
                'setter' => fn (Voie $v, array $prises) => $v->remplacerPrises($prises),
                'constraints' => [new Count(min: 1, minMessage: 'Marquez au moins une prise sur la photo.')],
            ])
            // L'ordre compte : le système doit être défini avant la cotation.
            ->add('systeme', EnumType::class, [
                'class' => SystemeCotation::class,
                'label' => 'Échelle',
                'expanded' => true,
                'choice_label' => fn (SystemeCotation $s) => $s->libelle(),
            ])
            ->add('cotationFr', EnumType::class, [
                'class' => CotationFr::class,
                'label' => 'Cotation',
                'required' => false,
                'placeholder' => '—',
                'choice_label' => fn (CotationFr $c) => $c->libelle(),
            ])
            ->add('cotationCouleur', EnumType::class, [
                'class' => CotationCouleur::class,
                'label' => 'Couleur',
                'required' => false,
                'placeholder' => '—',
                'choice_label' => fn (CotationCouleur $c) => $c->libelle(),
            ])
            ->add('description', TextareaType::class, ['label' => 'Description / consignes', 'required' => false]);

        $builder->get('prises')->addModelTransformer(new PrisesJsonTransformer());
    }

    /** Sans nouvelle photo, il faut en avoir choisi une existante. */
    private static function exigerUnePhoto(?UploadedFile $fichier, ExecutionContextInterface $context): void
    {
        $voie = $context->getRoot()->getData();
        if (null === $fichier && null === $voie?->getPhoto()) {
            $context->addViolation('Envoyez une photo ou choisissez-en une existante.');
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Voie::class,
            // false : formulaire réduit au nom et à la description (voie validée)
            'trace' => true,
        ]);
        $resolver->setAllowedTypes('trace', 'bool');
        $resolver->setRequired('secteur');
        $resolver->setAllowedTypes('secteur', Secteur::class);
    }
}
