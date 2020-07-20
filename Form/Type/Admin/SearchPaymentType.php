<?php
/**
 * This file is part of Stripe4
 *
 * Copyright(c) Akira Kurozumi <info@a-zumi.net>
 *
 * https://a-zumi.net
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\Stripe4\Form\Type\Admin;


use Eccube\Form\Type\Master\OrderStatusType;
use Eccube\Form\Type\Master\PaymentType;
use Plugin\Stripe4\Form\Master\PaymentStatusType;
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
            ->add('PaymentStatuses', PaymentStatusType::class, [
                'multiple' => true,
                'expanded' => true
            ]);
    }
}
