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

namespace Plugin\Stripe4\Form\Type\Admin\Stripe;

use Plugin\Stripe4\Entity\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('capture', ChoiceType::class, [
                'choices' => [
                    trans('stripe.admin.capture.actual_sales') => true,
                    trans('stripe.admin.capture.provisional_sales') => false,
                ],
                'expanded' => false,
                'multiple' => false,
            ]);

        $builder
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
                $form = $event->getForm();

                if (
                    !getenv('STRIPE_PUBLIC_KEY') ||
                    !getenv('STRIPE_SECRET_KEY')
                ) {
                    $form->addError(new FormError('APIキーが設定されていません。'));
                }
            });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
        ]);
    }
}
