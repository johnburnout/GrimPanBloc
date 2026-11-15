<?php

namespace App\Form\Reglages;

use App\Site\InformationsSalle;
use App\Site\ReglementInterieur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Informations pratiques sur la salle (voir InformationsSalle), affichées sur la
 * page « Informations », et règlement intérieur en PDF (voir ReglementInterieur).
 * Tous les champs sont facultatifs.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class SalleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $texte = ['required' => false, 'empty_data' => ''];

        $horaires = $builder->create('horaires', FormType::class, ['label' => false]);
        foreach (array_keys(InformationsSalle::JOURS) as $jour) {
            $horaires->add($jour, TextType::class, $texte + [
                'constraints' => [new Length(max: InformationsSalle::LONGUEUR_MAX_HORAIRE, maxMessage: 'Horaires trop longs ({{ limit }} caractères au plus).')],
            ]);
        }

        $builder
            ->add('adresse', TextareaType::class, $texte + [
                'constraints' => [new Length(max: InformationsSalle::LONGUEUR_MAX_ADRESSE, maxMessage: 'Adresse trop longue ({{ limit }} caractères au plus).')],
            ])
            ->add('coordonnees', TextType::class, $texte + [
                'constraints' => [new Callback(static function (?string $saisie, ExecutionContextInterface $contexte): void {
                    if ('' !== trim((string) $saisie) && null === InformationsSalle::analyserCoordonnees((string) $saisie)) {
                        $contexte->addViolation('Coordonnées invalides : saisissez la latitude puis la longitude, par exemple 44.8312, 1.3254.');
                    }
                })],
            ])
            ->add('site_web', UrlType::class, $texte + [
                'default_protocol' => 'https',
                'invalid_message' => 'Adresse du site internet invalide.',
                'constraints' => [
                    new Url(message: 'Adresse du site internet invalide.', protocols: ['http', 'https'], requireTld: true),
                    new Length(max: 255),
                ],
            ])
            ->add('horaires_affiches', CheckboxType::class, ['required' => false])
            ->add($horaires)
            ->add('horaires_remarque', TextType::class, $texte + [
                'constraints' => [new Length(max: InformationsSalle::LONGUEUR_MAX_REMARQUE, maxMessage: 'Remarque trop longue ({{ limit }} caractères au plus).')],
            ])
            ->add('hauteur', NumberType::class, [
                'required' => false,
                'scale' => 1,
                'html5' => true,
                'invalid_message' => 'Hauteur invalide.',
                'constraints' => [new Range(min: 0.5, max: 100, notInRangeMessage: 'La hauteur doit être comprise entre {{ min }} et {{ max }} m.')],
            ])
            ->add('surface', IntegerType::class, [
                'required' => false,
                'invalid_message' => 'Surface invalide.',
                'constraints' => [new Range(min: 1, max: 100000, notInRangeMessage: 'La surface doit être comprise entre {{ min }} et {{ max }} m².')],
            ])
            ->add('autres', TextareaType::class, $texte + [
                'trim' => false,
                'constraints' => [new Length(max: InformationsSalle::LONGUEUR_MAX_AUTRES, maxMessage: 'Texte trop long ({{ limit }} caractères au plus).')],
            ])
            ->add('supprimer_reglement', CheckboxType::class, ['required' => false, 'mapped' => false])
            ->add('reglement', FileType::class, [
                'required' => false,
                'mapped' => false,
                'constraints' => [new File(
                    maxSize: ReglementInterieur::TAILLE_MAX,
                    mimeTypes: ['application/pdf'],
                    maxSizeMessage: 'Le règlement intérieur ne doit pas dépasser 10 Mo.',
                    mimeTypesMessage: 'Le règlement intérieur doit être un fichier PDF.',
                )],
            ]);
    }

    public function getBlockPrefix(): string
    {
        return 'salle';
    }
}
