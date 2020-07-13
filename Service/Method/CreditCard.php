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

namespace Plugin\Stripe4\Service\Method;


use Doctrine\ORM\EntityManagerInterface;
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
use Plugin\Stripe4\Entity\Team;
use Plugin\Stripe4\Repository\ConfigRepository;
use Plugin\Stripe4\Repository\PaymentStatusRepository;
use Plugin\Stripe4\Repository\TeamRepository;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;
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

    /**
     * @var TeamRepository
     */
    private $teamRepository;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(
        OrderStatusRepository $orderStatusRepository,
        PaymentStatusRepository $paymentStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        EccubeConfig $eccubeConfig,
        ConfigRepository $configRepository,
        TeamRepository $teamRepository,
        EntityManagerInterface $entityManager
    )
    {
        $this->orderStatusRepository = $orderStatusRepository;
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->eccubeConfig = $eccubeConfig;
        $this->config = $configRepository->get();
        $this->teamRepository = $teamRepository;
        $this->entityManager = $entityManager;

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
        $result = new PaymentResult();

        try {
            log_info(sprintf("%s::create", PaymentIntent::class));
            $intent = PaymentIntent::retrieve($this->Order->getStripePaymentIntentId());

            // 受注ステータスを新規受付へ変更
            $OrderStatus = $this->orderStatusRepository->find(OrderStatus::NEW);
            $this->Order->setOrderStatus($OrderStatus);

            if ($this->config->getCapture()) {
                $intent->capture();

                // 決済ステータスを実売上へ変更
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::ACTUAL_SALES);
                $this->Order->setStripePaymentStatus($PaymentStatus);
            } else {
                // 決済ステータスを仮売上へ変更
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::PROVISIONAL_SALES);
                $this->Order->setStripePaymentStatus($PaymentStatus);
            }

            if ($intent->customer) {
                $team = $this->teamRepository->findOneBy([
                    "Customer" => $this->Order->getCustomer(),
                    "stripe_customer_id" => $intent->customer,
                    "stripe_payment_method_id" => $intent->payment_method
                ]);

                if (null === $team) {
                    $team = new Team();
                    $team
                        ->setCustomer($this->Order->getCustomer())
                        ->setStripeCustomerId($intent->customer)
                        ->setStripePaymentMethodId($intent->payment_method);
                    $this->entityManager->persist($team);
                }
            }

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

            // purchaseFlow::rollbackを呼び出し, 購入処理をロールバックする。
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
