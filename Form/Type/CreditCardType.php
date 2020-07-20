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

namespace Plugin\Stripe4\Form\Type;


use Plugin\Stripe4\Entity\CreditCard;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreditCardType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class' => CreditCard::class,
            'expanded' => false,
            'multiple' => false,
            'required' => false,
            'placeholder' => false,
            'choice_label' => function (CreditCard $creditCard) {
                return $creditCard->getStripePaymentMethodId();
            },
            'choice_value' => function (?CreditCard $creditCard) {
                return $creditCard ? $creditCard->getStripePaymentMethodId() : '';
            },
        ]);
    }

    public function getParent()
    {
        return EntityType::class;
    }
}
