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
use Eccube\Controller\AbstractShoppingController;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Service\CartService;
use Eccube\Service\MailService;
use Eccube\Service\OrderHelper;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Plugin\Stripe4\Entity\Config;
use Plugin\Stripe4\Entity\PaymentStatus;
use Plugin\Stripe4\Entity\CreditCard;
use Plugin\Stripe4\Repository\ConfigRepository;
use Plugin\Stripe4\Repository\PaymentStatusRepository;
use Plugin\Stripe4\Repository\CreditCardRepository;
use Stripe\Customer;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Refund;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class StripeController
 * @package Plugin\Stripe4\Controller
 *
 * @Route("/shopping/stripe")
 */
class PaymentController extends AbstractShoppingController
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var CreditCardRepository
     */
    private $creditCardRepository;

    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var MailService
     */
    private $mailService;

    /**
     * @var PaymentStatusRepository
     */
    private $paymentStatusRepository;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ParameterBag
     */
    private $parameterBag;

    public function __construct(
        CartService $cartService,
        OrderHelper $orderHelper,
        EccubeConfig $eccubeConfig,
        CreditCardRepository $creditCardRepository,
        OrderStatusRepository $orderStatusRepository,
        OrderRepository $orderRepository,
        MailService $mailService,
        PaymentStatusRepository $paymentStatusRepository,
        ConfigRepository $configRepository,
        ParameterBag $parameterBag
    )
    {
        $this->eccubeConfig = $eccubeConfig;
        Stripe::setApiKey($this->eccubeConfig['stripe_secret_key']);

        $this->cartService = $cartService;
        $this->orderHelper = $orderHelper;
        $this->creditCardRepository = $creditCardRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->orderRepository = $orderRepository;
        $this->mailService = $mailService;
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->config = $configRepository->get();
        $this->parameterBag = $parameterBag;
    }

    /**
     * @return RedirectResponse
     * @throws \Stripe\Exception\ApiErrorException
     *
     * @Route("/payment", name="shopping_stripe_payment")
     */
    public function payment(): RedirectResponse
    {
        // 受注情報の取得
        /** @var Order $Order 受注情報の取得 */
        $Order = $this->parameterBag->get('stripe.Order');

        if (!$Order) {
            logs('stripe')->info('受注情報が存在しません');

            return $this->redirectToRoute('shopping_error');
        }

        $paymentMethodId = $Order->getStripePaymentMethodId();

        try {
            if (null !== $paymentMethodId) {
                logs('stripe')->info('決済処理開始', [$Order->getId()]);

                $paymentIntentData = [
                    "amount" => (int)$Order->getPaymentTotal(),
                    "currency" => $this->eccubeConfig["currency"],
                    "payment_method" => $paymentMethodId,
                    "confirmation_method" => "manual",
                    "confirm" => true,
                    "capture_method" => $this->config->getCapture() ? "automatic" : "manual",
                ];

                if ($Order->getCustomer()) {
                    /** @var CreditCard $creditCard */
                    $creditCard = $this->creditCardRepository->findOneBy(['stripe_payment_method_id' => $paymentMethodId]);
                    if ($creditCard) {
                        $paymentIntentData['customer'] = $creditCard->getStripeCustomerId();
                    }

                    if ($Order->getStripeSavingCard()) {
                        $stripeCustomer = Customer::create([
                            "email" => $Order->getCustomer()->getEmail()
                        ]);
                        logs('stripe')->info($stripeCustomer->status);
                        $paymentIntentData['customer'] = $stripeCustomer->id;
                        $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
                        $paymentMethod->attach(['customer' => $stripeCustomer->id]);
                    }
                }

                logs('stripe')->info('PaymentIntent生成', [$Order->getId()]);
                $intent = PaymentIntent::create($paymentIntentData);
                $Order->setStripePaymentIntentId($intent->id);
                $this->entityManager->flush();
                logs('stripe')->info($intent->status);

                if ($intent->status === "requires_action") {
                    $intent->confirm([
                        'return_url' => $this->generateUrl('shopping_stripe_callback', [], UrlGeneratorInterface::ABSOLUTE_URL)
                    ]);
                }
            } else {
                throw new CardException('クレジットカード情報が正しくありません。');
            }

            return $this->generateResponse($intent, $Order);

        } catch (\Exception $e) {
            logs('stripe')->error($e->getMessage());

            if (isset($intent)) {
                $this->createRefund($intent);
            }

            $this->rollbackOrder($Order);

            $this->addError($e->getMessage());

            return $this->redirectToRoute('shopping_error');
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws \Stripe\Exception\ApiErrorException
     *
     * @Route("/callback", name="shopping_stripe_callback")
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            if (null !== $request->query->get('payment_intent')) {
                $intent = PaymentIntent::retrieve($request->query->get('payment_intent'));
                if ($intent->status == "requires_confirmation") {
                    $intent->confirm([
                        'return_url' => $this->generateUrl('shopping_stripe_callback', [], UrlGeneratorInterface::ABSOLUTE_URL)
                    ]);
                }

                /** @var Order $Order */
                $Order = $this->orderRepository->findOneBy([
                    'stripe_payment_method_id' => $intent->payment_method,
                    'OrderStatus' => OrderStatus::PENDING
                ]);

                if (null === $Order) {
                    throw new \Exception("受注情報が存在しません");
                }
            } else {
                throw new CardException('決済エラー');
            }

            return $this->generateResponse($intent, $Order);

        } catch (\Exception $e) {
            logs('stripe')->error($e->getMessage());

            if (isset($intent)) {
                $this->createRefund($intent);
            }

            $this->addError($e->getMessage());

            return $this->redirectToRoute('shopping_error');
        }
    }

    /**
     * @param PaymentIntent $intent
     * @param Order $Order
     * @return RedirectResponse
     * @throws \Eccube\Service\PurchaseFlow\PurchaseException
     * @throws \Stripe\Exception\ApiErrorException
     */
    protected function generateResponse(PaymentIntent $intent, Order $Order): RedirectResponse
    {
        logs('stripe')->info($intent->status);

        switch ($intent->status) {
            case "requires_action":
            case "requires_source_action":
                logs('stripe')->info($intent->description);
                return $this->redirect($intent->next_action->redirect_to_url->url);
            case "requires_payment_method":
            case "requires_source":
                // Card was not properly authenticated, suggest a new payment method
                logs('stripe')->error($intent->description);

                // 決済ステータスを未決済へ変更
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::OUTSTANDING);
                $Order->setStripePaymentStatus($PaymentStatus);

                $this->rollbackOrder($Order);

                $message = "Your card was denied, please provide a new payment method";
                logs('stripe')->error($message);
                return $this->redirectToRoute('shopping_error');
            case "succeeded":
            case "requires_capture":
                logs('stripe')->info('受注ステータスを新規受付へ変更', [$Order->getId()]);
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
                $Order->setOrderStatus($OrderStatus);

                if ($this->config->getCapture()) {
                    logs('stripe')->info('決済ステータスを実売上へ変更', [$Order->getId()]);
                    $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::ACTUAL_SALES);
                    $Order->setStripePaymentStatus($PaymentStatus);
                } else {
                    logs('stripe')->info('決済ステータスを仮売上へ変更', [$Order->getId()]);
                    $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::PROVISIONAL_SALES);
                    $Order->setStripePaymentStatus($PaymentStatus);
                }

                // クレジットカード情報を保存する場合
                if ($intent->customer) {
                    $paymentMethod = PaymentMethod::retrieve($intent->payment_method);

                    $creditCard = $this->creditCardRepository->findOneBy([
                        "fingerprint" => $paymentMethod->card->fingerprint
                    ]);

                    // DBに未登録の場合保存
                    if (null === $creditCard) {
                        logs('stripe')->info('クレジットカード情報を保存');
                        $creditCard = new CreditCard();
                        $creditCard
                            ->setCustomer($Order->getCustomer())
                            ->setStripeCustomerId($intent->customer)
                            ->setStripePaymentMethodId($intent->payment_method)
                            ->setFingerprint($paymentMethod->card->fingerprint)
                            ->setBrand($paymentMethod->card->brand)
                            ->setLast4($paymentMethod->card->last4);
                        $this->entityManager->persist($creditCard);
                    }
                }

                logs('stripe')->info('purchaseFlow::commitを呼び出し、購入処理をさせる', [$Order->getId()]);
                $this->purchaseFlow->commit($Order, new PurchaseContext());

                $this->completeShopping($Order);

                return $this->redirectToRoute('shopping_complete');
            default:
                return $this->redirectToRoute('shopping_error');
        }
    }

    /**
     * @param Order $Order
     */
    protected function completeShopping(Order $Order)
    {
        logs('stripe')->info('注文メールの送信を行います', [$Order->getId()]);
        $this->mailService->sendOrderMail($Order);

        logs('stripe')->info('カートをクリアします', [$Order->getId()]);
        $this->cartService->clear();

        logs('stripe')->info('受注IDをセッションにセット', [$Order->getId()]);
        $this->session->set(OrderHelper::SESSION_ORDER_ID, $Order->getId());

        $this->entityManager->flush();

        logs('stripe')->info('注文処理が完了しました. 購入完了画面へ遷移します.', [$Order->getId()]);
    }

    /**
     * @param PaymentIntent $intent
     * @throws \Stripe\Exception\ApiErrorException
     */
    protected function createRefund(PaymentIntent $intent)
    {
        if ($intent->status === 'succeeded') {
            logs('stripe')->info('返金処理を行います');
            $refund = Refund::create(['payment_intent' => $intent->id]);
            logs('stripe')->info($refund->status);
        }
    }

    /**
     * @param Order $Order
     */
    protected function rollbackOrder(Order $Order)
    {
        logs('stripe')->info('受注ステータスを購入処理中へ変更');
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
        $Order->setOrderStatus($OrderStatus);

        logs('stripe')->info('仮確定の取り消し');
        $this->purchaseFlow->rollback($Order, new PurchaseContext());

        $this->entityManager->flush();
    }

}
