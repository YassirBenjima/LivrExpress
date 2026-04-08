<?php

namespace App\Form;

use App\Entity\Colis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ColisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('orderNumber', TextType::class, [
                'label' => '№ Commande',
                'attr' => [
                    'inputmode' => 'numeric',
                    'pattern' => '[0-9]*',
                    'placeholder' => 'Ex: 1001',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    Colis::TYPE_SIMPLE => Colis::TYPE_SIMPLE,
                    Colis::TYPE_STOCK => Colis::TYPE_STOCK,
                ],
            ])
            ->add('recipient', TextType::class, [
                'label' => 'Destinataire',
                'mapped' => false,
                'required' => false,
            ])
            ->add('city', ChoiceType::class, [
                'label' => 'Ville',
                'choices' => $options['city_choices'],
                'placeholder' => 'Choisir une ville',
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix',
                'scale' => 2,
            ])
            ->add('packageOption', ChoiceType::class, [
                'label' => 'Colis',
                'choices' => [
                    'Ne pas ouvrir le colis' => 'Ne pas ouvrir le colis',
                    'Ouvrir le colis' => 'Ouvrir le colis',
                ],
                'data' => $options['default_package_option'],
                'mapped' => false,
                'required' => true,
            ])
            ->add('phoneNumber', TextType::class, [
                'label' => 'Numero de telephone',
            ])
            ->add('neighborhood', TextType::class, [
                'label' => 'Quartier',
            ])
            ->add('comment', TextType::class, [
                'label' => 'Commentaire',
                'required' => false,
            ])
            ->add('productNature', TextType::class, [
                'label' => 'Nature de produit',
            ])
            ->add('replacePackage', CheckboxType::class, [
                'label' => "Colis a remplacer (Le colis sera remplace avec l'ancien a la livraison.)",
                'mapped' => false,
                'required' => false,
            ])
            ->add('oldColis', ChoiceType::class, [
                'label' => 'Ancien colis',
                'choices' => $options['old_colis_choices'],
                'placeholder' => 'Choisir un ancien colis',
                'placeholder_attr' => [
                    'disabled' => 'disabled',
                ],
                'mapped' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Colis::class,
            'city_choices' => [],
            'old_colis_choices' => [],
            'default_package_option' => 'Ne pas ouvrir le colis',
        ]);

        $resolver->setAllowedTypes('city_choices', 'array');
        $resolver->setAllowedTypes('old_colis_choices', 'array');
        $resolver->setAllowedTypes('default_package_option', ['string', 'null']);
    }
}
