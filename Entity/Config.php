<?php


namespace Plugin\Stripe4\Entity;


use Doctrine\ORM\Mapping as ORM;

if (!class_exists(Config::class)) {
    /**
     * Class Config
     * @package Plugin\Stripe4\Entity
     *
     * @ORM\Table(name="plg_stripe_config")
     * @ORM\Entity(repositoryClass="Plugin\Stripe4\Repository\ConfigRepository")
     */
    class Config
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
         * @return $this
         */
        public function setCapture(bool $capture): self
        {
            $this->capture = $capture;

            return $this;
        }
    }
}
