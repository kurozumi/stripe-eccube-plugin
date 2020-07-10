<?php


namespace Plugin\Stripe4\Form\Type\Admin;


use Doctrine\ORM\EntityRepository;
use Eccube\Form\Type\Master\OrderStatusType;
use Eccube\Form\Type\Master\PaymentType;
use Plugin\Stripe4\Entity\PaymentStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class SearchPaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('Payments', PaymentType::class, [
                'multiple' => true,
                'expanded' => true
            ])
            ->add('OrderStatuses', OrderStatusType::class, [
                'multiple' => true,
                'expanded' => true
            ])
            ->add('PaymentStatuses', EntityType::class, [
                'class' => PaymentStatus::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->orderBy('p.id', 'ASC');
                },
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true
            ]);
    }
}
