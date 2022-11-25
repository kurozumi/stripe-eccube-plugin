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

namespace Plugin\Stripe4\Form\Extension;

use Doctrine\Common\Collections\ArrayCollection;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Order;
use Eccube\Form\Type\Shopping\OrderType;
use Eccube\Form\Type\ToggleSwitchType;
use Plugin\Stripe4\Service\Method\CreditCard;
use Stripe\PaymentMethod;
use Stripe\Stripe;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

class CreditCardExtension extends AbstractTypeExtension
{
    /**
     * @var EccubeConfig
     */
    private $eccubeConfig;

    public function __construct(EccubeConfig $eccubeConfig)
    {
        $this->eccubeConfig = $eccubeConfig;
        Stripe::setApiKey($this->eccubeConfig['stripe_secret_key']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['skip_add_form']) {
            return;
        }

        $builder
            ->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
                /** @var FormInterface $form */
                $form = $event->getForm();
                /** @var Order $order */
                $order = $event->getData();

                if ($order->getPayment()->getMethodClass() === CreditCard::class) {
                    $form
                        ->add('stripe_payment_method_id', HiddenType::class, [
                            'error_bubbling' => false,
                        ]);

                    if ($customer = $order->getCustomer()) {
                        $form
                            ->add('stripe_save_card', ToggleSwitchType::class, [
                                'mapped' => true,
                                'label' => 'カード情報を保存する',
                            ]);

                        $cards = new ArrayCollection();
                        if ($customer->hasStripeCustomerId()) {
                            $cards = PaymentMethod::all([
                                'customer' => $customer->getStripeCustomerId(),
                                'type' => 'card',
                            ]);
                            $cards = new ArrayCollection($cards->data);
                        }

                        $form
                            ->add('cards', ChoiceType::class, [
                                'mapped' => false,
                                'choices' => $cards,
                                'multiple' => false,
                                'expanded' => true,
                                'required' => false,
                                'placeholder' => false,
                                'choice_label' => function (PaymentMethod $paymentMethod) {
                                    return $paymentMethod->card->brand.' •••• '.$paymentMethod->card->last4;
                                },
                                'choice_value' => function (?PaymentMethod $paymentMethod) {
                                    return $paymentMethod ? $paymentMethod->id : '';
                                },
                            ]);
                    }
                }
            });

        $builder
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
                /** @var FormInterface $form */
                $form = $event->getForm();
                /** @var Order $order */
                $order = $event->getData();

                if ($order->getPayment()->getMethodClass() === CreditCard::class) {
                    if ($form->get('redirect_to')->getData()) {
                        return;
                    }

                    if ($form->has('stripe_payment_method_id')) {
                        if (null === $order->getStripePaymentMethodId()) {
                            $form->get('stripe_payment_method_id')->addError(new FormError(trans('クレジットカード情報を入力してください。')));
                        }
                    }
                }
            });
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
