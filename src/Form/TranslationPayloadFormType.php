<?php

namespace App\Form;

use Survos\LibreTranslateBundle\Dto\TranslationPayload;
use Survos\LinguaBundle\Dto\BatchRequest;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TranslationPayloadFormType extends AbstractType
{
    public function __construct(
        #[Autowire('%kernel.enabled_locales%')] private array $enabledLocales,

    )
    {

    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach ($this->enabledLocales as $locale) {
            $choices[$locale] = $locale;
        }
        $builder
            ->add('target', ChoiceType::class, [
                'multiple' => true,
                'choices' => $choices,
            ])
//            ->add('target')
            ->add('engine')
//            ->add('transport')
//            ->add('forceDispatch', CheckboxType::class, ['required' => false])
            ->add('insertNewStrings', CheckboxType::class, [
                'help' => "Insert and dispatch translation requests",
                'required' => false])
            ->add('source', ChoiceType::class, [
                'choices' => $choices,
                'multiple' => false,
                'expanded' => true,
            ])
            ->add('texts', TextareaType::class, [
                'attr' => [
                    'cols' => 80,
                    'rows' => 5,
                ]

            ])
//            ->add('callbackUrl')
        ;

        $builder->get('texts')
            ->addModelTransformer(new CallbackTransformer(
                fn ($tagsAsArray): string => implode("\n", $tagsAsArray),
                fn ($tagsAsString): array => array_map('trim', explode("\n", $tagsAsString)),
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
            'data_class' => BatchRequest::class,
        ]);
    }
}
