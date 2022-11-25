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

namespace Plugin\Stripe4\Tests\Form\Master;

use Eccube\Tests\Form\Type\AbstractTypeTestCase;
use Plugin\Stripe4\Entity\PaymentStatus;
use Plugin\Stripe4\Form\Type\Master\PaymentStatusType;
use Plugin\Stripe4\Repository\PaymentStatusRepository;
use Symfony\Component\Form\FormInterface;

class PaymentStatusTypeTest extends AbstractTypeTestCase
{
    /** @var FormInterface */
    protected $form;

    /** @var PaymentStatusRepository */
    protected $paymentStatusRepository;

    public function setUp(): void
    {
        parent::setUp();

        $container = self::$kernel->getContainer();

        $this->paymentStatusRepository = $this->entityManager->getRepository(PaymentStatus::class);

        $this->form = $this->formFactory
            ->createBuilder(PaymentStatusType::class, null, [
                'csrf_protection' => false,
            ])
            ->getForm();
    }

    public function test正常テスト()
    {
        $this->form->submit(1);
        self::assertTrue($this->form->isValid());
        self::assertEquals($this->form->getData(), $this->paymentStatusRepository->find(1));
    }

    public function test表示テスト()
    {
        $view = $this->form->createView();
        $choices = $view->vars['choices'];

        $data = [];
        foreach ($choices as $choice) {
            $data[] = $choice->data;
        }
        $paymentStatuses = $this->paymentStatusRepository->findBy([], ['sort_no' => 'ASC']);
        self::assertEquals($data, $paymentStatuses);
    }

    public function test範囲外の数値テスト()
    {
        $this->form->submit(50);
        self::assertFalse($this->form->isValid());
    }

    public function test範囲外の値テスト()
    {
        $this->form->submit('a');
        self::assertFalse($this->form->isValid());
    }
}
