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
 * @package Plugin\Stripe4\Entity
 *
 * @ORM\Table(name="plg_stripe_payment_status")
 * @ORM\Entity(repositoryClass="Plugin\Stripe4\Repository\PaymentStatusRepository")
 */
class PaymentStatus extends AbstractMasterEntity
{
    /**
     * 未決済
     */
    const OUTSTANDING = 1;

    /**
     * 有効性チェック済み
     */
    const ENABLED = 2;

    /**
     * 仮売上
     */
    const PROVISIONAL_SALES = 3;

    /**
     * 実売上
     */
    const ACTUAL_SALES = 4;

    /**
     * キャンセル
     */
    const CANCEL = 5;

    /**
     * 返金
     */
    const REFUND = 6;
}
