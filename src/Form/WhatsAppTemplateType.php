<?php

namespace App\Form;

use App\Entity\WhatsAppTemplate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WhatsAppTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'class' => 'kt-input',
                    'placeholder' => 'titre du message',
                    'maxlength' => '150',
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => [
                    'class' => 'kt-textarea',
                    'rows' => 5,
                    'placeholder' => 'Votre message WhatsApp...',
                    'maxlength' => '2000',
                    'data-whatsapp-message-input' => 'true',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WhatsAppTemplate::class,
        ]);
    }
}
