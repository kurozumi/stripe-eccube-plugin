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

namespace Plugin\Stripe4\Tests\Web;

use Eccube\Common\Constant;
use Eccube\Entity\Delivery;
use Eccube\Entity\Payment;
use Eccube\Entity\PaymentOption;
use Eccube\Entity\Product;
use Eccube\Repository\DeliveryRepository;
use Eccube\Repository\PaymentRepository;
use Eccube\Repository\ProductRepository;
use Eccube\Tests\Web\AbstractShoppingControllerTestCase;
use Plugin\Stripe4\Service\Method\CreditCard;
use Symfony\Component\Filesystem\Filesystem;

class ShoppingControllerTest extends AbstractShoppingControllerTestCase
{
    /**
     * @var DeliveryRepository
     */
    private $deliveryRepository;

    /**
     * @var PaymentRepository
     */
    private $paymentRepository;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    private $themeFrontDefaultDir;

    private $themeFrontDir;

    public function setUp(): void
    {
        parent::setUp();

        $container = self::$kernel->getContainer();

        $this->deliveryRepository = $this->entityManager->getRepository(Delivery::class);
        $this->paymentRepository = $this->entityManager->getRepository(Payment::class);
        $this->productRepository = $this->entityManager->getRepository(Product::class);

        $this->themeFrontDefaultDir = $this->eccubeConfig->get('eccube_theme_front_default_dir');
        $this->themeFrontDir = $this->eccubeConfig->get('eccube_theme_front_dir');
    }

    public function testお支払い方法にクレジットカード決済が表示されるか()
    {
        /** @var Delivery $delivery 販売種別Aのサンプル業者 */
        $delivery = $this->deliveryRepository->find(1);

        // 販売種別Aにクレジットカード決済を登録
        $this->createPaymentOption($delivery);

        $Customer = $this->createCustomer();

        // カート画面
        $this->scenarioCartIn($Customer);

        // 確認画面
        $crawler = $this->scenarioConfirm();

        self::assertStringContainsString('クレジットカード決済', $crawler->html());
    }

    public function testクレジットカード決済を選択したときにクレジットカード情報項目が表示されるか()
    {
        $file = file_get_contents($this->themeFrontDefaultDir.'/Shopping/index.twig');
        $file = str_replace(
            '<div class="ec-input {{ has_errors(form.Payment) ? \' error\' }}">{{ form_errors(form.Payment) }}</div>',
            '<div class="ec-input {{ has_errors(form.Payment) ? \' error\' }}">{{ form_errors(form.Payment) }}</div>{{ include(\'@Stripe4/credit.twig\', ignore_missing=true) }}',
            $file
        );

        $fs = new Filesystem();
        $fs->dumpFile(
            $this->themeFrontDir.'/Shopping/index.twig',
            $file
        );

        /** @var Delivery $delivery 販売種別Aのサンプル業者 */
        $delivery = $this->deliveryRepository->find(1);

        // 販売種別Aにクレジットカード決済を登録
        $paymentOption = $this->createPaymentOption($delivery);

        $Customer = $this->createCustomer();

        // カート画面
        $this->scenarioCartIn($Customer);

        // 確認画面
        $this->scenarioConfirm($Customer);

        // デフォルトがクレジットカード決済になっているので一度別の決済方法に変更
        $crawler = $this->scenarioRedirectTo($Customer, [
            '_shopping_order' => [
                'Shippings' => [
                    [
                        'Delivery' => $delivery->getId(),
                        'DeliveryTime' => null,
                    ],
                ],
                'Payment' => 1,
                Constant::TOKEN_NAME => '_dummy',
            ],
        ]);

        self::assertStringNotContainsString('クレジットカード情報', $crawler->html());

        // クレジットカード決済を選択
        $crawler = $this->scenarioRedirectTo($Customer, [
            '_shopping_order' => [
                'Shippings' => [
                    [
                        'Delivery' => $delivery->getId(),
                        'DeliveryTime' => null,
                    ],
                ],
                'Payment' => $paymentOption->getPaymentId(),
                Constant::TOKEN_NAME => '_dummy',
            ],
        ]);

        self::assertContains('クレジットカード情報', $crawler->html());
    }

    /**
     * @param Delivery $delivery
     *
     * @return PaymentOption
     */
    private function createPaymentOption(Delivery $delivery): PaymentOption
    {
        /** @var Payment $payment クレジットカード決済 */
        $payment = $this->paymentRepository->findOneBy([
            'method_class' => CreditCard::class,
        ]);

        $paymentOption = new PaymentOption();
        $paymentOption
            ->setDeliveryId($delivery->getId())
            ->setDelivery($delivery)
            ->setPaymentId($payment->getId())
            ->setPayment($payment);
        $this->entityManager->persist($paymentOption);
        $this->entityManager->flush();

        return $paymentOption;
    }
}
