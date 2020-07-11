<?php


namespace Plugin\Stripe4\Service\Method;


use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentMethod;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\Stripe4\Entity\Config;
use Plugin\Stripe4\Entity\PaymentStatus;
use Plugin\Stripe4\Repository\ConfigRepository;
use Plugin\Stripe4\Repository\PaymentStatusRepository;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Exception\CardException;
use Stripe\Stripe;
use Stripe\Token;
use Symfony\Component\Form\FormInterface;

class CreditCard implements PaymentMethodInterface
{
    /**
     * @var Order
     */
    protected $Order;

    /**
     * @var FormInterface
     */
    protected $form;

    /**
     * @var OrderStatusRepository
     */
    protected $orderStatusRepository;

    /**
     * @var PaymentStatusRepository
     */
    protected $paymentStatusRepository;

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var Config
     */
    protected $config;

    public function __construct(
        OrderStatusRepository $orderStatusRepository,
        PaymentStatusRepository $paymentStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        EccubeConfig $eccubeConfig,
        ConfigRepository $configRepository
    )
    {
        $this->orderStatusRepository = $orderStatusRepository;
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->eccubeConfig = $eccubeConfig;
        $this->config = $configRepository->get();

        Stripe::setApiKey($this->eccubeConfig['stripe_secret_key']);
    }

    /**
     * @inheritDoc
     *
     * 注文確認画面遷移時に呼び出される
     *
     * クレジットカードの有効性チェックを行う
     */
    public function verify()
    {
        // TODO: Implement verify() method.

        // 決済ステータスを有効性チェック済みへ変更
        $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::ENABLED);
        $this->Order->setStripePaymentStatus($PaymentStatus);

        // クレジットカード番号の末尾4桁を保存
        $token = $this->Order->getStripeToken();
        $tokenObj = Token::retrieve($token);
        $this->Order->setStripeCardNoLast4($tokenObj->card->last4);

        $result = new PaymentResult();
        $result->setSuccess(true);

        return $result;
    }

    /**
     * @inheritDoc
     *
     * 注文時に呼び出される。
     *
     * クレジットカードの決済処理を行う。
     */
    public function checkout()
    {
        // TODO: Implement checkout() method.
        $token = $this->Order->getStripeToken();

        $result = new PaymentResult();

        try {
            log_info(sprintf("%s::create", Charge::class));
            $charge = Charge::create([
                'amount' => $this->Order->getPaymentTotal(),
                'currency' => $this->eccubeConfig['currency'],
                "source" => $token,
                "capture" => $this->config->getCapture()
            ]);

            // 受注ステータスを新規受付へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
            $this->Order->setOrderStatus($OrderStatus);

            // 決済ステータスを実売上へ変更
            $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::ACTUAL_SALES);
            $this->Order->setStripePaymentStatus($PaymentStatus);

            // Stripeの課金IDを保存
            $this->Order->setStripeChargeId($charge['id']);

            // purchaseFlow::commitを呼び出し、購入処理をさせる
            $this->purchaseFlow->commit($this->Order, new PurchaseContext());

            $result->setSuccess(true);

        } catch (\Exception $e) {
            // 受注ステータスを購入処理中へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $this->Order->setOrderStatus($OrderStatus);

            // 決済ステータスを未決済へ変更
            $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::OUTSTANDING);
            $this->Order->setStripePaymentStatus($PaymentStatus);

            $this->purchaseFlow->rollback($this->Order, new PurchaseContext());

            log_error(sprintf("%s: %s", CreditCard::class, $e->getMessage()));
            $result->setSuccess(false);
            $result->setErrors([trans('stripe.shopping.checkout.error')]);
        }

        return $result;
    }

    /**
     * @inheritDoc
     *
     * 注文時に呼び出される
     *
     * 注文ステータス、決済ステータスを更新する。
     */
    public function apply()
    {
        // TODO: Implement apply() method.
        // 受注ステーテスを決済処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
        $this->Order->setOrderStatus($OrderStatus);

        $this->purchaseFlow->prepare($this->Order, new PurchaseContext());
    }

    /**
     * @inheritDoc
     */
    public function setFormType(FormInterface $form)
    {
        // TODO: Implement setFormType() method.
        $this->form = $form;
    }

    /**
     * @inheritDoc
     */
    public function setOrder(Order $Order)
    {
        // TODO: Implement setOrder() method.
        $this->Order = $Order;
    }
}
