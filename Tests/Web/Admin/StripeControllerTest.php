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

namespace Plugin\Stripe4\Tests\Web\Admin;


use Eccube\Common\Constant;
use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use Symfony\Component\Filesystem\Filesystem;

class StripeControllerTest extends AbstractAdminWebTestCase
{
    public function setUp()
    {
        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    public function testページ表示チェック()
    {
        $this->client->request('GET', $this->generateUrl('stripe4_admin_stripe_config'));

        self::assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function test公開可能キーとシークレットキーを登録したらenvファイルに追記されるか()
    {
        $envFile = self::$container->getParameter('kernel.project_dir') . '/.env';

        $fs = new Filesystem();
        $fs->copy($envFile, $envFile . '.backup');

        $this->client->request('POST', $this->generateUrl('stripe4_admin_stripe_user'), [
            'user' => [
                'public_key' => 'dummy',
                'secret_key' => 'dummy',
                Constant::TOKEN_NAME => 'dummy'
            ]
        ]);

        $env = file_get_contents($envFile);

        $keys = [
            'STRIPE_PUBLIC_KEY',
            'STRIPE_SECRET_KEY',
        ];

        foreach ($keys as $key) {
            $pattern = '/^(' . $key . ')=(.*)/m';
            if (preg_match($pattern, $env, $matches)) {
                self::assertEquals('dummy', $matches[2]);
            } else {
                self::fail(sprintf("%sが見つかりませんでした。", $key));
            }
        }

        $fs->rename($envFile . '.backup', $envFile, true);
    }
}
