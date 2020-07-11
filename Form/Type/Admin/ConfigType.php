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
                    trans('stripe.admin.caputure.actual_sales') => true,
                    trans('stripe.admin.caputure.provisional_sales') => false
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
