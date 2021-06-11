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
use Eccube\Annotation\EntityExtension;

/**
 * Trait CustomerTrait
 * @package Plugin\Stripe4\Entity
 *
 * @EntityExtension("Eccube\Entity\Customer")
 */
trait CustomerTrait
{
    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private $stripe_customer_id;

    /**
     * @return string|null
     */
    public function getStripeCustomerId(): ?string
    {
        return $this->stripe_customer_id;
    }

    /**
     * @param string $stripe_customer_id
     * @return $this
     */
    public function setStripeCustomerId(string $stripe_customer_id): self
    {
        $this->stripe_customer_id = $stripe_customer_id;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasStripeCustomerId(): bool
    {
        return null !== $this->stripe_customer_id;
    }
}
