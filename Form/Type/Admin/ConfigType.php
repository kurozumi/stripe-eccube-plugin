<?php


namespace Plugin\Stripe4\Form\Type\Admin;


use Plugin\Stripe4\Entity\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('capture', ChoiceType::class, [
                'choices' => [
                    '仮実同時処理' => true,
                    '仮売上処理' => false
                ],
                'expanded' => false,
                'multiple' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class
        ]);
    }
}
