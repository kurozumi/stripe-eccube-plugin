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

namespace Plugin\Stripe4\Form\Extension\Admin;

use Eccube\Form\Type\Admin\OrderType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class OrderTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('stripe_payment_intent_id', TextType::class, [
                'label' => 'stripe.admin.order.edit.payment_indent.label',
                'disabled' => true,
                'eccube_form_options' => [
                    'auto_render' => true,
                ],
            ]);
    }

    /**
     * @return string
     */
    public function getExtendedType(): string
    {
        return OrderType::class;
    }

    /**
     * @return iterable
     */
    public static function getExtendedTypes(): iterable
    {
        yield OrderType::class;
    }
}
