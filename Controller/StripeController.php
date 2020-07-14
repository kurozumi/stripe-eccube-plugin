<?php
/**
 * This file is part of ec-cube
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
use Eccube\Service\CartService;
use Eccube\Service\OrderHelper;
use Stripe\Customer;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class StripeController
 * @package Plugin\Stripe4\Controller
 *
 * @Route("/stripe")
 */
class StripeController extends AbstractController
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    public function __construct(
        CartService $cartService,
        OrderHelper $orderHelper,
        EccubeConfig $eccubeConfig
    )
    {
        $this->cartService = $cartService;
        $this->orderHelper = $orderHelper;
        $this->eccubeConfig = $eccubeConfig;

        Stripe::setApiKey($this->eccubeConfig['stripe_secret_key']);
    }

    /**
     * @Route("/payment", name="stripe_payment", methods={"POST"})
     */
    public function payment(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            throw new BadRequestHttpException();
        }

        $this->isTokenValid();

        $input = file_get_contents('php://input');
        $body = json_decode($input, true);

        $paymentMethodId = $body['paymentMethodId'];
        $isSavingCard = $body['isSavingCard'];

        $preOrderId = $this->cartService->getPreOrderId();
        $Order = $this->orderHelper->getPurchaseProcessingOrder($preOrderId);

        if (!$Order) {
            return $this->json([
                'error' => '購入処理中の受注が存在しません'
            ]);
        }

        try {
            if (null !== $paymentMethodId) {
                // paymentIntentIdが登録されていたらキャンセル処理
                $paymentIntentId = $Order->getStripePaymentIntentId();
                if ($paymentIntentId) {
                    PaymentIntent::retrieve($paymentIntentId)->cancel();
                }

                $paymentIntentData = [
                    "amount" => (int)$Order->getPaymentTotal(),
                    "currency" => $this->eccubeConfig["currency"],
                    "payment_method" => $paymentMethodId,
                    "capture_method" => "manual",
                ];

                if ($isSavingCard) {
                    $customer = Customer::create();
                    $paymentIntentData['customer'] = $customer->id;
                    $paymentIntentData['setup_future_usage'] = 'off_session';
                }

                $intent = PaymentIntent::create($paymentIntentData);
            } else {
                throw new CardException();
            }
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ]);
        }

        return $this->json($this->generateResponse($intent));
    }

    public function generateResponse($intent)
    {
        switch ($intent->status) {
            case "requires_action":
            case "requires_source_action":
            case "requires_confirmation":
                // Card requires authentication
                return [
                    'requiresAction' => true,
                    'paymentIntentId' => $intent->id,
                    'clientSecret' => $intent->client_secret
                ];
            case "requires_payment_method":
            case "requires_source":
                // Card was not properly authenticated, suggest a new payment method
                return [
                    'error' => "Your card was denied, please provide a new payment method"
                ];
            case "succeeded":
                // Payment is complete, authentication not required
                // To cancel the payment after capture you will need to issue a Refund (https://stripe.com/docs/api/refunds)
                return ['clientSecret' => $intent->client_secret];
        }
    }

}
