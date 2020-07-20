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


use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
     * @var Collection
     *
     * @ORM\OneToMany(targetEntity="Plugin\Stripe4\Entity\CreditCard", mappedBy="Customer", cascade={"persist", "remove"})
     */
    private $creditCards;

    /**
     * @return Collection
     */
    public function getCreditCards(): Collection
    {
        if (null === $this->creditCards) {
            $this->creditCards = new ArrayCollection();
        }

        return $this->creditCards;
    }

    /**
     * @param CreditCard $creditCard
     * @return $this
     */
    public function addCreditCard(CreditCard $creditCard): self
    {
        if (null === $this->creditCards) {
            $this->creditCards = new ArrayCollection();
        }

        if (!$this->creditCards->contains($creditCard)) {
            $this->creditCards[] = $creditCard;
            $creditCard->setCustomer($this);
        }

        return $this;
    }

    /**
     * @param CreditCard $creditCard
     * @return $this
     */
    public function removeCreditCard(CreditCard $creditCard): self
    {
        if (null === $this->creditCards) {
            $this->creditCards = new ArrayCollection();
        }

        if ($this->creditCards->contains($creditCard)) {
            $this->creditCards->removeElement($creditCard);

            if($creditCard->getCustomer() === $this) {
                $creditCard->setCustomer(null);
            }
        }

        return $this;
    }

}
