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

namespace Plugin\Stripe4\Tests\Form\Admin\Stripe;

use Eccube\Tests\Form\Type\AbstractTypeTestCase;
use Plugin\Stripe4\Form\Type\Admin\Stripe\UserType;

class UserTypeTest extends AbstractTypeTestCase
{
    protected $form;

    protected $formData = [
        'public_key' => 'dummy',
        'secret_key' => 'dummy',
    ];

    public function setUp(): void
    {
        parent::setUp();

        $this->form = $this->formFactory
            ->createBuilder(UserType::class, null, [
                'csrf_protection' => false,
            ])
            ->getForm();
    }

    public function testPUBLICKEYが空はエラー()
    {
        $this->formData['public_key'] = '';
        $this->form->submit($this->formData);
        self::assertFalse($this->form->isValid());
    }

    public function testSECRETKEYが空はエラー()
    {
        $this->formData['secret_key'] = '';
        $this->form->submit($this->formData);
        self::assertFalse($this->form->isValid());
    }
}
