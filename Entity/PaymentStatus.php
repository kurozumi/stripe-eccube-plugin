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

namespace Plugin\Stripe4\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\Master\AbstractMasterEntity;

/**
 * Class PaymentStatus
 *
 * @ORM\Table(name="plg_stripe_payment_status")
 * @ORM\Entity(repositoryClass="Plugin\Stripe4\Repository\PaymentStatusRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class PaymentStatus extends AbstractMasterEntity
{
    /**
     * 未決済
     */
    public const OUTSTANDING = 1;

    /**
     * 有効性チェック済み
     */
    public const ENABLED = 2;

    /**
     * 仮売上
     */
    public const PROVISIONAL_SALES = 3;

    /**
     * 実売上
     */
    public const ACTUAL_SALES = 4;

    /**
     * キャンセル
     */
    public const CANCEL = 5;

    /**
     * 返金
     */
    public const REFUND = 6;
}
