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

namespace Plugin\Stripe4\Repository;


use Eccube\Repository\AbstractRepository;
use Plugin\Stripe4\Entity\CreditCard;
use Symfony\Bridge\Doctrine\RegistryInterface;

class CreditCardRepository extends AbstractRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, CreditCard::class);
    }
}
