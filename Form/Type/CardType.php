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


use Plugin\Stripe4\Entity\Team;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CardType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class' => Team::class,
            'expanded' => false,
            'multiple' => false,
            'placeholder' => false,
            'choice_label' => function (Team $team) {
                return $team->getStripePaymentMethodId();
            },
            'choice_value' => function (?Team $team) {
                return $team ? $team->getStripePaymentMethodId() : '';
            },
            'query_builder' => null
        ]);
    }

    public function getParent()
    {
        return EntityType::class;
    }
}
