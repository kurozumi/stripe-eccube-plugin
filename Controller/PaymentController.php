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
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class StripeController
 * @package Plugin\Stripe4\Controller
 *
 * @Route("/shopping")
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
     * @Route("/stripe_payment", name="stripe_payment")
     */
    public function payment(Request $request)
    {
        // 受注情報の取得
        /** @var Order $Order 受注情報の取得 */
        $Order = $this->parameterBag->get('stripe.Order');

        if (!$Order) {
            log_info('[Stripe][注文処理] 受注情報が存在しません.');

            return $this->redirectToRoute('shopping_error');
        }

        $paymentMethodId = $Order->getStripePaymentMethodId();

        try {
            if (null !== $paymentMethodId) {
                log_info("[Stripe]決済処理開始");

                $paymentIntentData = [
                    "amount" => (int)$Order->getPaymentTotal(),
                    "currency" => $this->eccubeConfig["currency"],
                    "payment_method" => $paymentMethodId,
                    "confirmation_method" => "manual",
                    "confirm" => true,
                    "capture_method" => $this->config->getCapture() ? "automatic" : "manual",
                ];

                if($Order->getCustomer())  {
                    if ($Order->getCustomer()->getCreditCards()->count() > 0) {
                        $stripeCustomer = $Order->getCustomer()->getCreditCards()->first()->getStripeCustomerId();
                        $paymentIntentData['customer'] = $stripeCustomer;
                    } else {
                        if ($Order->getStripeSavingCard()) {
                            $stripeCustomer = Customer::create([
                                "email" => $Order->getCustomer()->getEmail()
                            ]);
                            $paymentIntentData['customer'] = $stripeCustomer->id;
                        }
                    }
                }

                log_info("[Stripe]PaymentIntent生成");
                $intent = PaymentIntent::create($paymentIntentData);

                if ($intent->status == "requires_action") {
                    $intent->confirm([
                        'return_url' => $this->generateUrl('stripe_reciever', [], UrlGeneratorInterface::ABSOLUTE_URL)
                    ]);
                }
            } else {
                throw new CardException('[Stripe]クレジットカード情報が正しくありません。');
            }
        } catch (\Exception $e) {
            log_error("[Stripe]" . $e->getMessage());

            $this->rollbackOrder($Order);

            $this->addError($e->getMessage());
            return $this->redirectToRoute('shopping_error');
        }

        return $this->generateResponse($intent, $Order);
    }

    /**
     * @param Request $request
     *
     * @Route("/stripe_reciever", name="stripe_reciever")
     */
    public function reciever(Request $request)
    {
        try {
            if (null !== $request->query->get('payment_intent')) {
                $intent = PaymentIntent::retrieve($request->query->get('payment_intent'));
                if ($intent->status == "requires_confirmation") {
                    $intent->confirm([
                        'return_url' => $this->generateUrl('stripe_reciever', [], UrlGeneratorInterface::ABSOLUTE_URL)
                    ]);
                }

                $Order = $this->orderRepository->findOneBy([
                    'stripe_payment_method_id' => $intent->payment_method,
                    'OrderStatus' => OrderStatus::PENDING
                ]);

                if (null === $Order) {
                    throw new \Exception("[Stripe]受注情報が存在しません。");
                }
            } else {
                throw new CardException('[Stripe]決済エラー。');
            }
        } catch (\Exception $e) {
            log_error("[Stripe]" . $e->getMessage());

            $this->addError($e->getMessage());
            return $this->redirectToRoute('shopping_error');
        }

        return $this->generateResponse($intent, $Order);
    }

    public function generateResponse(PaymentIntent $intent, Order $Order)
    {
        switch ($intent->status) {
            case "requires_action":
            case "requires_source_action":
                log_info("[Stripe]3Dセキュア認証ページへリダイレクト");
                return $this->redirect($intent->next_action->redirect_to_url->url);
            case "requires_payment_method":
            case "requires_source":
                // Card was not properly authenticated, suggest a new payment method
                log_error('[Stripe]決済エラー');

                // 決済ステータスを未決済へ変更
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::OUTSTANDING);
                $Order->setStripePaymentStatus($PaymentStatus);

                $this->rollbackOrder($Order);

                $message = "Your card was denied, please provide a new payment method";
                $this->addError($message);
                return $this->redirectToRoute("shopping_error");
            case "succeeded":
            case "requires_capture":
                // 受注ステータスを新規受付へ変更
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
                $Order->setOrderStatus($OrderStatus);

                // PaymentIntent保存
                $Order->setStripePaymentIntentId($intent->id);

                if ($this->config->getCapture()) {
                    // 決済ステータスを実売上へ変更
                    $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::ACTUAL_SALES);
                    $Order->setStripePaymentStatus($PaymentStatus);
                } else {
                    // 決済ステータスを仮売上へ変更
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
                        log_info("[Stripe]クレジットカード情報を保存");
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

                // purchaseFlow::commitを呼び出し、購入処理をさせる
                $this->purchaseFlow->commit($Order, new PurchaseContext());

                log_info('[Stripe][注文処理] カートをクリアします.', [$Order->getId()]);
                $this->cartService->clear();

                // 受注IDをセッションにセット
                $this->session->set(OrderHelper::SESSION_ORDER_ID, $Order->getId());

                // メール送信
                log_info('[注文処理] 注文メールの送信を行います.', [$Order->getId()]);
                $this->mailService->sendOrderMail($Order);
                $this->entityManager->flush();

                log_info('[注文処理] 注文処理が完了しました. 購入完了画面へ遷移します.', [$Order->getId()]);

                return $this->redirectToRoute("shopping_complete");
        }
    }


    /**
     * @param Order $Order
     */
    private function rollbackOrder(Order $Order)
    {
        // 受注ステータスを購入処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
        $Order->setOrderStatus($OrderStatus);

        // 仮確定の取り消し
        $this->purchaseFlow->rollback($Order, new PurchaseContext());

        $this->entityManager->flush();
    }

}
