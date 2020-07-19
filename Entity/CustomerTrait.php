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
     * @ORM\OneToMany(targetEntity="Plugin\Stripe4\Entity\Team", mappedBy="Customer", cascade={"persist", "remove"})
     */
    private $Teams;

    /**
     * @return Collection
     */
    public function getTeams(): Collection
    {
        if (null === $this->Teams) {
            $this->Teams = new ArrayCollection();
        }

        return $this->Teams;
    }

    /**
     * @param Team $team
     * @return $this
     */
    public function addTeam(Team $team): self
    {
        if (null === $this->Teams) {
            $this->Teams = new ArrayCollection();
        }

        if (!$this->Teams->contains($team)) {
            $this->Teams[] = $team;
            $team->setCustomer($this);
        }

        return $this;
    }

    /**
     * @param Team $team
     * @return $this
     */
    public function removeTeam(Team $team): self
    {
        if (null === $this->Teams) {
            $this->Teams = new ArrayCollection();
        }

        if ($this->Teams->contains($team)) {
            $this->Teams->removeElement($team);

            if($team->getCustomer() === $this) {
                $team->setCustomer(null);
            }
        }

        return $this;
    }

}
