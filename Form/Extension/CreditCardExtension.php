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


use Eccube\Entity\Order;
use Eccube\Form\Type\Shopping\OrderType;
use Plugin\Stripe4\Form\Type\CreditCardType;
use Plugin\Stripe4\Service\Method\CreditCard;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class CreditCardExtension extends AbstractTypeExtension
{
    /**
     * @var SessionInterface
     */
    private $session;

    public function __construct(
        SessionInterface $session
    )
    {
        $this->session = $session;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['skip_add_form']) {
            return;
        }

        $builder
            ->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
                /** @var FormBuilderInterface $form */
                $form = $event->getForm();
                /** @var Order $order */
                $order = $event->getData();

                if ($order->getPayment()->getMethodClass() === CreditCard::class) {
                    $form
                        ->add('stripe_payment_method_id', HiddenType::class, [
                            'mapped' => true,
                            'constraints' => [
                                new NotBlank()
                            ]
                        ])
                        ->add('is_saving_card', ChoiceType::class, [
                            'mapped' => false,
                            'choices' => [
                                'カード情報を保存する' => true,
                            ],
                            'expanded' => true,
                            'multiple' => true
                        ]);

                    if ($Customer = $order->getCustomer()) {
                        $form
                            ->add('stripe_customer', HiddenType::class, [
                                'mapped' => false,
                                'data' => $order->getCustomer()->getCreditCards()->count() ? $order->getCustomer()->getCreditCards()->first()->getStripeCustomerId() : ''
                            ])
                            ->add('cards', CreditCardType::class, [
                                'mapped' => false,
                                'expanded' => true,
                                'choices' => $order->getCustomer()->getCreditCards()
                            ]);
                    }
                }
            });

        $builder
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
                /** @var FormBuilderInterface $form */
                $form = $event->getForm();
                /** @var Order $order */
                $order = $event->getData();

                if ($Customer = $order->getCustomer()) {
                    $this->session->remove(CreditCard::IS_SAVING_CARD);
                    if ($form->has('is_saving_card')) {
                        $is_saving_card = $form->get('is_saving_card')->getData();
                        if ($is_saving_card) {
                            $this->session->set(CreditCard::IS_SAVING_CARD, true);
                        }
                    }
                }
            });
    }

    /**
     * @inheritDoc
     */
    public function getExtendedType()
    {
        // TODO: Implement getExtendedType() method.
        return OrderType::class;
    }
}
