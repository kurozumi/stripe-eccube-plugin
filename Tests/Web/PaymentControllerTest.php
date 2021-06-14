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


use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;

class PaymentControllerTest extends AbstractAdminWebTestCase
{
    public function setUp()
    {
        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    public function test直接アクセスした場合はエラーページへリダイレクト()
    {
        $this->client->request('GET', $this->generateUrl('shopping_stripe_payment'));

        self::assertTrue($this->client->getResponse()->isRedirect($this->generateUrl('shopping_error')));
    }

}
