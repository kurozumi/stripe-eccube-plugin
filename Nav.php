<?php


namespace Plugin\Stripe4;


use Eccube\Common\EccubeNav;

class Nav implements EccubeNav
{

    /**
     * @inheritDoc
     */
    public static function getNav()
    {
        return [
            'order' => [
                'children' => [
                    'stripe_admin_payment_status' => [
                        'name' => 'stripe.admin.nav.payment_list',
                        'url' => 'stripe_admin_payment_status'
                    ]
                ]
            ],
            'stripe' => [
                'name' => 'stripe.admin.config.title',
                'icon' => 'fa-cc-stripe',
                'children' => [
                    'stripe_admin_config' => [
                        'name' => 'stripe.admin.nav.config',
                        'url' => 'stripe4_admin_config'
                    ]
                ]
            ]
        ];
    }
}
