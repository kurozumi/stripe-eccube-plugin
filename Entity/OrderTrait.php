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
 * Trait OrderTrait
 * @package Plugin\Stripe4\Entity
 *
 * @EntityExtension("Eccube\Entity\Order")
 */
trait OrderTrait
{
    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $stripe_payment_method_id;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $stripe_payment_intent_id;

    /**
     * @var bool
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private $stripe_saving_card = false;

    /**
     * @var PaymentStatus
     * @ORM\ManyToOne(targetEntity="Plugin\Stripe4\Entity\PaymentStatus")
     * @ORM\JoinColumn(name="stripe_payment_status_id", referencedColumnName="id")
     */
    private $StripePaymentStatus;

    /**
     * @return string|null
     */
    public function getStripePaymentMethodId(): ?string
    {
        return $this->stripe_payment_method_id;
    }

    /**
     * @param string|null $stripe_payment_method_id
     * @return $this
     */
    public function setStripePaymentMethodId(?string $stripe_payment_method_id): self
    {
        $this->stripe_payment_method_id = $stripe_payment_method_id;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripe_payment_intent_id;
    }

    /**
     * @param string|null $stripe_payment_intent_id
     * @return $this
     */
    public function setStripePaymentIntentId(?string $stripe_payment_intent_id): self
    {
        $this->stripe_payment_intent_id = $stripe_payment_intent_id;

        return $this;
    }

    /**
     * @return bool
     */
    public function getStripeSavingCard(): bool
    {
        return $this->stripe_saving_card;
    }

    /**
     * @param bool $stripe_saving_card
     * @return $this
     */
    public function setStripeSavingCard(bool $stripe_saving_card): self
    {
        $this->stripe_saving_card = $stripe_saving_card;

        return $this;
    }

    /**
    /**
     * @return PaymentStatus|null
     */
    public function getStripePaymentStatus(): ?PaymentStatus
    {
        return $this->StripePaymentStatus;
    }

    /**
     * @param PaymentStatus|null $paymentStatus
     * @return $this
     */
    public function setStripePaymentStatus(?PaymentStatus $paymentStatus): self
    {
        $this->StripePaymentStatus = $paymentStatus;

        return $this;
    }
}
