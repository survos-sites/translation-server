<?php

namespace App\Form;

use Survos\LibreTranslateBundle\Dto\TranslationPayload;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TranslationPayloadFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
//            ->add('from', ChoiceType::class, [
//                'choices' => ['English'=>'en','Spanish' => 'es','French' =>'fr'],
//            ])
            ->add('from')
            ->add('engine')
            ->add('to', ChoiceType::class, [
                'choices' => ['English'=>'en','Spanish' => 'es','French' =>'fr'],
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('text', TextareaType::class, [
                'attr' => [
                    'cols' => 80,
                    'rows' => 5,
                ]

            ])
            ->add('callbackUrl')
        ;

        $builder->get('text')
            ->addModelTransformer(new CallbackTransformer(
                fn ($tagsAsArray): string => implode("\n", $tagsAsArray),
                fn ($tagsAsString): array => explode("\n", $tagsAsString),
            ))
        ;
    }

//    public function getBlockPrefix()
//    {
//        return 'json';
//    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TranslationPayload::class,
        ]);
    }
}
