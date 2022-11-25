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
use Eccube\Entity\AbstractEntity;

if (!class_exists(Config::class)) {
    /**
     * Class Config
     *
     * @ORM\Table(name="plg_stripe_config")
     * @ORM\Entity(repositoryClass="Plugin\Stripe4\Repository\ConfigRepository")
     * @ORM\HasLifecycleCallbacks()
     */
    class Config extends AbstractEntity
    {
        /**
         * @var int
         *
         * @ORM\Column(name="id", type="integer", options={"unsigned": true})
         * @ORM\Id()
         * @ORM\GeneratedValue(strategy="IDENTITY")
         */
        private $id;

        /**
         * @var bool
         *
         * @ORM\Column(name="capture", type="boolean", options={"default":true})
         *
         * trueは仮実同時処理、falseは仮売上処理
         */
        private $capture;

        /**
         * @return int
         */
        public function getId(): int
        {
            return $this->id;
        }

        /**
         * @return bool
         */
        public function getCapture(): bool
        {
            return $this->capture;
        }

        /**
         * @param bool $capture
         *
         * @return $this
         */
        public function setCapture(bool $capture): self
        {
            $this->capture = $capture;

            return $this;
        }
    }
}
