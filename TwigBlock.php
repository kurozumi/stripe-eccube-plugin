<?php

namespace Plugin\Stripe4;

use Eccube\Common\EccubeTwigBlock;

class TwigBlock implements EccubeTwigBlock
{
    /**
     * @return array
     */
    public static function getTwigBlock()
    {
        return [
            '@Stripe4/credit.twig'
        ];
    }
}
