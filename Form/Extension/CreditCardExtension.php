<?php


namespace Plugin\Stripe4\Form\Extension;


use Eccube\Entity\Order;
use Eccube\Form\Type\Shopping\OrderType;
use Plugin\Stripe4\Service\Method\CreditCard;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;

class CreditCardExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['skip_add_form']) {
            return;
        }

        $builder
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
                $form = $event->getForm();
                /** @var Order $order */
                $order = $event->getData();

                if ($order->getPayment()->getMethodClass() === CreditCard::class) {
                    $form->add('stripe_token', HiddenType::class, [
                        'mapped' => true,
                        'constraints' => [
                            new NotBlank()
                        ]
                    ]);
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
