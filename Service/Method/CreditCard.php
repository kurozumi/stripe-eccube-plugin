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
use Eccube\Service\Payment\PaymentDispatcher;
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
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CreditCard implements PaymentMethodInterface
{
    const IS_SAVING_CARD = 'stripe.is_saving_card';

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

    /**
     * @var ParameterBag
     */
    private $parameterBag;

    /**
     * @var SessionInterface
     */
    private $session;

    public function __construct(
        OrderStatusRepository $orderStatusRepository,
        PaymentStatusRepository $paymentStatusRepository,
        PurchaseFlow $shoppingPurchaseFlow,
        EccubeConfig $eccubeConfig,
        ConfigRepository $configRepository,
        TeamRepository $teamRepository,
        EntityManagerInterface $entityManager,
        ParameterBag $parameterBag,
        SessionInterface $session
    )
    {
        $this->orderStatusRepository = $orderStatusRepository;
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->purchaseFlow = $shoppingPurchaseFlow;
        $this->eccubeConfig = $eccubeConfig;
        $this->config = $configRepository->get();
        $this->teamRepository = $teamRepository;
        $this->entityManager = $entityManager;
        $this->parameterBag = $parameterBag;
        $this->session = $session;

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
        $result->setSuccess(true);

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

        // 3Dセキュア画面へリダイレクト
        $dispatcher = new PaymentDispatcher();
        $dispatcher->setRoute('stripe_payment');
        $dispatcher->setForward(false);

        return $dispatcher;
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
