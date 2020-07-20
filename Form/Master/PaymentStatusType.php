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

namespace Plugin\Stripe4\Form\Master;


use Eccube\Form\Type\MasterType;
use Plugin\Stripe4\Entity\PaymentStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentStatusType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class' => PaymentStatus::class
        ]);
    }

    public function getBlockPrefix()
    {
        return 'payment_status';
    }

    public function getParent()
    {
        return MasterType::class;
    }
}
