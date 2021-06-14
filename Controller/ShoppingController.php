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

namespace Plugin\Stripe4\Controller;


use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Customer;
use Stripe\PaymentMethod;
use Stripe\Stripe;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class CreditCardController
 * @package Plugin\Stripe4\Controller
 *
 * @Route("/shopping/creadit_card")
 */
class ShoppingController extends AbstractController
{
    public function __construct(EccubeConfig $eccubeConfig)
    {
        $this->eccubeConfig = $eccubeConfig;
        Stripe::setApiKey($this->eccubeConfig['stripe_secret_key']);
    }

    /**
     * @Route("/detach/{pm}", name="shopping_credit_card_detach")
     */
    public function detach($pm)
    {
        /** @var Customer $customer */
        $customer = $this->getUser();

        if (!$customer instanceof Customer) {
            return $this->redirectToRoute('shopping_login');
        }

        $paymentMethod = PaymentMethod::retrieve($pm);
        if ($customer->getStripeCustomerId() === $paymentMethod->customer) {
            $paymentMethod->detach();
        }

        return $this->redirectToRoute('shopping');
    }
}
