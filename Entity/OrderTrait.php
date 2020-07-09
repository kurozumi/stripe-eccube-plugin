<?php


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
    private $stripe_token;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    private $stripe_charge_id;

    /**
     * @var PaymentStatus
     * @ORM\ManyToOne(targetEntity="Plugin\Stripe4\Entity\PaymentStatus")
     * @ORM\JoinColumn(name="stripe_payment_status_id", referencedColumnName="id")
     */
    private $StripePaymentStatus;

    /**
     * @return string|null
     */
    public function getStripeToken(): ?string
    {
        return $this->stripe_token;
    }

    /**
     * @param string|null $stripe_token
     * @return $this
     */
    public function setStripeToken(?string $stripe_token): self
    {
        $this->stripe_token = $stripe_token;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getStripeChargeId(): ?string
    {
        return $this->stripe_charge_id;
    }

    /**
     * @param string|null $stripe_charge_id
     * @return $this
     */
    public function setStripeChargeId(?string $stripe_charge_id): self
    {
        $this->stripe_charge_id = $stripe_charge_id;

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
