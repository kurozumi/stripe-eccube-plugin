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
use Plugin\Stripe4\Entity\Team;
use Plugin\Stripe4\Repository\ConfigRepository;
use Plugin\Stripe4\Repository\PaymentStatusRepository;
use Plugin\Stripe4\Repository\TeamRepository;
use Plugin\Stripe4\Service\Method\CreditCard;
use Stripe\Customer;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
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
     * @var TeamRepository
     */
    private $teamRepository;

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

    public function __construct(
        CartService $cartService,
        OrderHelper $orderHelper,
        EccubeConfig $eccubeConfig,
        TeamRepository $teamRepository,
        OrderStatusRepository $orderStatusRepository,
        OrderRepository $orderRepository,
        MailService $mailService,
        PaymentStatusRepository $paymentStatusRepository,
        ConfigRepository $configRepository
    )
    {
        $this->eccubeConfig = $eccubeConfig;
        Stripe::setApiKey($this->eccubeConfig['stripe_secret_key']);

        $this->cartService = $cartService;
        $this->orderHelper = $orderHelper;
        $this->teamRepository = $teamRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->orderRepository = $orderRepository;
        $this->mailService = $mailService;
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->config = $configRepository->get();
    }

    /**
     * @Route("/stripe_payment", name="stripe_payment")
     */
    public function payment(Request $request)
    {
        // ログイン状態のチェック
        if ($this->orderHelper->isLoginRequired()) {
            log_info('[Stripe][注文確認] 未ログインもしくはRememberMeログインのため, ログイン画面に遷移します.');

            return $this->redirectToRoute('shopping_login');
        }

        // 受注の存在チェック
        $preOrderId = $this->cartService->getPreOrderId();
        $Order = $this->getPurchasePendingOrder($preOrderId);
        if (!$Order) {
            log_info('[Stripe][注文処理] 購入処理中の受注が存在しません.', [$preOrderId]);

            return $this->redirectToRoute('shopping_error');
        }

        $paymentMethodId = $Order->getStripePaymentMethodId();
        $isSavingCard = $this->session->get(CreditCard::IS_SAVING_CARD);

        try {
            if (null !== $request->query->get('payment_intent')) {
                log_info("[Stripe]3Dセキュア通過済み");
                $intent = PaymentIntent::retrieve($request->query->get('payment_intent'));
                if($intent->status == "requires_confirmation") {
                    $intent->confirm([
                        'return_url' => $this->generateUrl('stripe_payment', [], UrlGeneratorInterface::ABSOLUTE_URL)
                    ]);
                }
            } elseif (null !== $paymentMethodId) {
                log_info("[Stripe]決済処理開始");
                $paymentIntentData = [
                    "amount" => (int)$Order->getPaymentTotal(),
                    "currency" => $this->eccubeConfig["currency"],
                    "payment_method" => $paymentMethodId,
                    "confirmation_method" => "manual",
                    "confirm" => true,
                    "capture_method" => $this->config->getCapture() ? "automatic" : "manual",
                ];

                /** @var \Eccube\Entity\Customer $Customer */
                $Customer = $this->getUser();

                /** @var Team $team */
                $team = $this->teamRepository->findOneBy([
                    "stripe_payment_method_id" => $paymentMethodId
                ]);

                if ($team) {
                    $paymentIntentData['customer'] = $team->getStripeCustomerId();
                } elseif ($isSavingCard && $Customer) {
                    $stripeCustomer = Customer::create([
                        "email" => $Customer->getEmail()
                    ]);
                    $paymentIntentData['customer'] = $stripeCustomer->id;
                    $paymentIntentData['payment_method'] = $paymentMethodId;
                    $paymentIntentData['setup_future_usage'] = 'off_session';
                }

                log_info("[Stripe]PaymentIntent生成");
                $intent = PaymentIntent::create($paymentIntentData);
                if($intent->status == "requires_action") {
                    $intent->confirm([
                        'return_url' => $this->generateUrl('stripe_payment', [], UrlGeneratorInterface::ABSOLUTE_URL)
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

    public function generateResponse(PaymentIntent $intent, Order $Order)
    {
        switch ($intent->status) {
            case "requires_action":
            case "requires_source_action":
                // Card requires authentication
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
                // Payment is complete, authentication not required
                // To cancel the payment after capture you will need to issue a Refund (https://stripe.com/docs/api/refunds)

                // 受注ステータスを新規受付へ変更
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
                $Order->setOrderStatus($OrderStatus);

                if ($this->config->getCapture()) {
                    // 決済ステータスを実売上へ変更
                    $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::ACTUAL_SALES);
                    $Order->setStripePaymentStatus($PaymentStatus);
                } else {
                    // 決済ステータスを仮売上へ変更
                    $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::PROVISIONAL_SALES);
                    $Order->setStripePaymentStatus($PaymentStatus);
                }

                if ($intent->customer) {
                    $team = $this->teamRepository->findOneBy([
                        "Customer" => $Order->getCustomer(),
                        "stripe_customer_id" => $intent->customer,
                        "stripe_payment_method_id" => $intent->payment_method
                    ]);

                    if (null === $team) {
                        log_info("[Stripe]Stripeカスタマー情報を保存");
                        $team = new Team();
                        $team
                            ->setCustomer($Order->getCustomer())
                            ->setStripeCustomerId($intent->customer)
                            ->setStripePaymentMethodId($intent->payment_method);
                        $this->entityManager->persist($team);
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
     * @param null $preOrderId
     * @return object|null
     */
    private function getPurchasePendingOrder($preOrderId = null)
    {
        if (null === $preOrderId) {
            return null;
        }

        return $this->orderRepository->findOneBy([
            'pre_order_id' => $preOrderId,
            'OrderStatus' => OrderStatus::PENDING,
        ]);
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
