<?php


namespace Plugin\Stripe4;


use Eccube\Entity\Payment;
use Eccube\Plugin\AbstractPluginManager;
use Plugin\Stripe4\Entity\Config;
use Plugin\Stripe4\Entity\PaymentStatus;
use Plugin\Stripe4\Repository\ConfigRepository;
use Plugin\Stripe4\Service\Method\CreditCard;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PluginManager extends AbstractPluginManager
{
    public function enable(array $meta, ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');

        /** @var ConfigRepository $configRepository */
        $configRepository = $entityManager->getRepository(Config::class);

        $Config = $configRepository->get();
        if(null === $Config) {
            $Config = new Config();
            $Config->setCapture(true);
            $entityManager->persist($Config);
            $entityManager->flush();
        }

        $this->createTokenPayment($container);
        $this->createPaymentStatuses($container);
    }

    private function createTokenPayment(ContainerInterface $container)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $paymentRepository = $entityManager->getRepository(Payment::class);

        $Payment = $paymentRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $Payment ? $Payment->getSortNo() + 1 : 1;

        $Payment = $paymentRepository->findOneBy(['method_class' => CreditCard::class]);
        if ($Payment) {
            return;
        }

        $Payment = new Payment();
        $Payment->setCharge(0);
        $Payment->setSortNo($sortNo);
        $Payment->setVisible(true);
        $Payment->setMethod("Stripe");
        $Payment->setMethodClass(CreditCard::class);

        $entityManager->persist($Payment);
        $entityManager->flush();
    }

    private function createMasterData(ContainerInterface $container, array $statuses, $class)
    {
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $i = 0;
        foreach ($statuses as $id => $name) {
            $PaymentStatus = $entityManager->find($class, $id);
            if (!$PaymentStatus) {
                $PaymentStatus = new $class;
            }
            $PaymentStatus->setId($id);
            $PaymentStatus->setName($name);
            $PaymentStatus->setSortNo($i++);
            $entityManager->persist($PaymentStatus);
        }
        $entityManager->flush();
    }

    private function createPaymentStatuses(ContainerInterface $container)
    {
        $statuses = [
            PaymentStatus::OUTSTANDING => '未決済',
            PaymentStatus::ENABLED => '有効性チェック済',
            PaymentStatus::PROVISIONAL_SALES => '仮売上',
            PaymentStatus::ACTUAL_SALES => '実売上',
            PaymentStatus::CANCEL => '返金'
        ];
        $this->createMasterData($container, $statuses, PaymentStatus::class);
    }

}
