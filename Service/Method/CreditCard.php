<?php


namespace Plugin\Stripe\Service\Method;


use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentMethod;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\Stripe\Entity\PaymentStatus;
use Plugin\Stripe\Repository\PaymentStatusRepository;
use Stripe\Charge;
use Stripe\Stripe;
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
    private $orderStatusRepository;

    /**
     * @var PaymentStatusRepository
     */
    private $paymentStatusRepository;

    /**
     * @var PurchaseFlow
     */
    private $purchaseFlow;

    /**
     * @var EccubeConfig
     */
    private $eccubeConfig;

    public function __construct(
        OrderStatusRepository $orderStatusRepository,
        PaymentStatusRepository $paymentStatusRepository,
        PurchaseFlow $purchaseFlow,
        EccubeConfig $eccubeConfig
    )
    {
        $this->orderStatusRepository = $orderStatusRepository;
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->purchaseFlow = $purchaseFlow;
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * @inheritDoc
     */
    public function verify()
    {
        // TODO: Implement verify() method.
        $result = new PaymentResult();
        $result->setSuccess(true);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function checkout()
    {
        // TODO: Implement checkout() method.
        $token = $this->Order->getStripeToken();

        Stripe::setApiKey($this->eccubeConfig['stripe_secret_key']);

        $charge = Charge::create([
            'source' => $token,
            'currency' => $this->eccubeConfig['currency'],
            'amount' => $this->Order->getPaymentTotal()
        ]);

        $result = new PaymentResult();

        if ($charge instanceof Charge) {
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
        }else{
            // 受注ステータスを購入処理中へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
            $this->Order->setOrderStatus($OrderStatus);

            // 決済ステータスを未決済へ変更
            $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::OUTSTANDING);
            $this->Order->setStripePaymentStatus($PaymentStatus);

            $this->purchaseFlow->rollback($this->Order, new PurchaseContext());

            $result->setSuccess(false);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        // TODO: Implement apply() method.
        // 受注ステーテスを決済処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
        $this->Order->setOrderStatus($OrderStatus);

        // 決済ステータスを未決済へ変更
        $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::OUTSTANDING);
        $this->Order->setStripePaymentStatus($PaymentStatus);

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