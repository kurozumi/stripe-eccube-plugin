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

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Service\Payment\PaymentDispatcher;
use Eccube\Service\Payment\PaymentMethodInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Plugin\Stripe4\Entity\PaymentStatus;
use Plugin\Stripe4\Repository\PaymentStatusRepository;
use Stripe\Stripe;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

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
     * @var ParameterBag
     */
    private $parameterBag;

    public function __construct(
        OrderStatusRepository $orderStatusRepository,
        PaymentStatusRepository $paymentStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        EccubeConfig $eccubeConfig,
        ParameterBag $parameterBag
    ) {
        $this->eccubeConfig = $eccubeConfig;
        Stripe::setApiKey($this->eccubeConfig['stripe_secret_key']);

        $this->orderStatusRepository = $orderStatusRepository;
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->parameterBag = $parameterBag;
    }

    /**
     * {@inheritDoc}
     *
     * 注文確認画面遷移時に呼び出される
     *
     * クレジットカードの有効性チェックを行う
     */
    public function verify()
    {
        // 決済ステータスを有効性チェック済みへ変更
        $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::ENABLED);
        $this->Order->setStripePaymentStatus($PaymentStatus);

        $result = new PaymentResult();
        $result->setSuccess(true);

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * 注文時に呼び出される。
     *
     * クレジットカードの決済処理を行う。
     */
    public function checkout()
    {
        $result = new PaymentResult();
        $result->setSuccess(true);

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * 注文時に呼び出される
     *
     * 注文ステータス、決済ステータスを更新する。
     */
    public function apply()
    {
        // 受注ステーテスを決済処理中へ変更
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PENDING);
        $this->Order->setOrderStatus($OrderStatus);

        $this->purchaseFlow->prepare($this->Order, new PurchaseContext());

        $this->parameterBag->set('stripe.Order', $this->Order);

        // 支払い処理へリダイレクト
        $dispatcher = new PaymentDispatcher();
        $dispatcher->setRoute('shopping_stripe_payment');
        $dispatcher->setForward(true);

        return $dispatcher;
    }

    /**
     * {@inheritDoc}
     */
    public function setFormType(FormInterface $form)
    {
        $this->form = $form;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setOrder(Order $Order)
    {
        $this->Order = $Order;

        return $this;
    }
}
