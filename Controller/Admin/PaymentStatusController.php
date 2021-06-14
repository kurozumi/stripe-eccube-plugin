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

namespace Plugin\Stripe4\Controller\Admin;


use Eccube\Common\Constant;
use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\PageMax;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\Master\PageMaxRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Service\OrderStateMachine;
use Eccube\Util\FormUtil;
use Knp\Component\Pager\PaginatorInterface;
use Plugin\Stripe4\Entity\PaymentStatus;
use Plugin\Stripe4\Form\Type\Admin\SearchPaymentType;
use Plugin\Stripe4\Repository\PaymentStatusRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class PaymentStatusController
 * @package Plugin\Stripe4\Controller\Admin
 *
 * 決済状況管理
 */
class PaymentStatusController extends AbstractController
{
    protected $bulkActions = [
        ['id' => 1, 'name' => '一括売上'],
        ['id' => 2, 'name' => '一括返金'],
        ['id' => 3, 'name' => '一括取消']
    ];

    /**
     * @var PaymentStatusRepository
     */
    protected $paymentStatusRepository;

    /**
     * @var PageMaxRepository
     */
    protected $pageMaxRepository;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var OrderStateMachine
     */
    private $orderStateMachine;

    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    public function __construct(
        PaymentStatusRepository $paymentStatusRepository,
        PageMaxRepository $pageMaxRepository,
        OrderRepository $orderRepository,
        EccubeConfig $eccubeConfig,
        OrderStateMachine $orderStateMachine,
        OrderStatusRepository $orderStatusRepository
    )
    {
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->pageMaxRepository = $pageMaxRepository;
        $this->orderRepository = $orderRepository;
        $this->eccubeConfig = $eccubeConfig;
        $this->orderStateMachine = $orderStateMachine;
        $this->orderStatusRepository = $orderStatusRepository;

        Stripe::setApiKey($this->eccubeConfig['stripe_secret_key']);
    }

    /**
     * @param Request $request
     * @param $page_no
     * @param PaginatorInterface $paginator
     * @return array
     *
     * 決済状況一覧画面
     *
     * @Route("/%eccube_admin_route%/stripe/payment_status", name="stripe_admin_payment_status")
     * @Route("/%eccube_admin_route%/stripe/payment_status/{page_no}", requirements={"page_no" = "\d+"}, name="stripe_admin_payment_status_pageno")
     * @Template("@Stripe4/admin/payment_status.twig")
     */
    public function index(Request $request, $page_no = null, PaginatorInterface $paginator)
    {
        $searchForm = $this->createForm(SearchPaymentType::class);

        /**
         * ページの表示件数は、以下の順に優先される。
         * - リクエストパラメータ
         * - セッション
         * - デフォルト値
         * また、セッションに保存する際は mtb_page_maxと照合し、一致した場合のみ保存する。
         */
        $page_count = $this->session->get('stripe.admin.payment_status.search.page_count',
            $this->eccubeConfig->get('eccube_default_page_count'));

        $page_count_param = (int)$request->get('page_count');
        $pageMaxis = $this->pageMaxRepository->findAll();

        if ($page_count_param) {
            /** @var PageMax $pageMax */
            foreach ($pageMaxis as $pageMax) {
                if ($page_count_param == $pageMax->getName()) {
                    $page_count = $pageMax->getName();
                    $this->session->set('stripe.admin.payment_status.search.page_count', $page_count);
                    break;
                }
            }
        }

        if ($request->isMethod('POST')) {
            $searchForm->handleRequest($request);

            if ($searchForm->isSubmitted() && $searchForm->isValid()) {
                /**
                 * 検索が実行された場合は、セッションに検索条件を保存する
                 * ページ番号は最初のページ番号に初期化する
                 */
                $page_no = 1;
                $searchData = $searchForm->getData();

                // 検索条件、ページ番号をセッションに保持。
                $this->session->set('stripe.admin.payment_status.search', FormUtil::getViewData($searchForm));
                $this->session->set('stripe.admin.payment_status.search.page_no', $page_no);
            } else {
                // 検索エラーの際は、詳細検索枠を開いてエラー表示する
                return [
                    'searchForm' => $searchForm->createView(),
                    'pagination' => [],
                    'pageMaxis' => $pageMaxis,
                    'page_no' => $page_no,
                    'page_count' => $page_count,
                    'has_errors' => true
                ];
            }
        } else {
            if (null !== $page_no || $request->get('resume')) {
                /**
                 * ページ送りの場合または、他画面から戻ってきた場合は、セッションから検索条件を復旧する。
                 */
                if ($page_no) {
                    // ページ送りで遷移した場合
                    $this->session->set('stripe.admin.payment_status.search.page_no', (int)$page_no);
                } else {
                    // 他画面から遷移した場合
                    $page_no = $this->session->get('stripe.admin.payment_status.search.page_no', 1);
                }
                $viewData = $this->session->get('stripe.admin.payment_status.search', []);
                $searchData = FormUtil::submitAndGetData($searchForm, $viewData);
            } else {
                /**
                 * 初期表示の場合。
                 */
                $page_no = 1;
                $searchData = [];

                // セッション中の検索条件、ページ番号を初期化。
                $this->session->set('stripe.admin.payment_status.search', $searchData);
                $this->session->set('stripe.admin.payment_status.search.page_no', $page_no);
            }
        }

        $qb = $this->createQueryBuilder($searchData);
        $pagination = $paginator->paginate(
            $qb,
            $page_no,
            $page_count
        );

        return [
            'searchForm' => $searchForm->createView(),
            'pagination' => $pagination,
            'pageMaxis' => $pageMaxis,
            'page_no' => $page_no,
            'page_count' => $page_count,
            'has_errors' => false,
            'bulkActions' => $this->bulkActions
        ];
    }

    /**
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     *
     * 一括処理
     *
     * @Route("/%eccube_admin_route%/stripe/payment_status/bulk_action/{id}", requirements={"id" = "\d+"}, name="stripe_admin_payment_status_bulk_action", methods={"POST"})
     */
    public function bulkAction(Request $request, $id)
    {
        $bulkAction = array_filter($this->bulkActions, function ($bulkAction) use ($id) {
            return $bulkAction["id"] == $id;
        });

        if (!$bulkAction) {
            throw new BadRequestHttpException();
        }

        $this->isTokenValid();

        /** @var Order[] $orders */
        $orders = $this->orderRepository->findBy(['id' => $request->get('ids')]);
        /** @var PaymentStatus $actualSales */
        $actualSales = $this->paymentStatusRepository->find(PaymentStatus::ACTUAL_SALES);
        /** @var PaymentStatus $refund */
        $refund = $this->paymentStatusRepository->find(PaymentStatus::REFUND);
        /** @var PaymentStatus $cancel */
        $cancel = $this->paymentStatusRepository->find(PaymentStatus::CANCEL);

        $success = 0;
        $errors = 0;
        /** @var Order $order */
        foreach ($orders as $order) {
            try {
                switch ($id) {
                    // 一括売上
                    case 1:
                        // 実売上処理
                        PaymentIntent::retrieve($order->getStripePaymentIntentId())->capture();
                        // 決済ステータスを実売上に変更
                        $order->setStripePaymentStatus($actualSales);
                        break;
                    // 一括返金
                    case 2:
                        // 払い戻し処理
                        Refund::create([
                            "payment_intent" => $order->getStripePaymentIntentId()
                        ]);
                        // 決済ステータスを返金に変更
                        $order->setStripePaymentStatus($refund);
                        break;
                    // 一括取消
                    case 3:
                        // 未キャプチャの支払いを取消
                        PaymentIntent::retrieve($order->getStripePaymentIntentId())->cancel();
                        // 決済ステータスをキャンセルに変更
                        $order->setStripePaymentStatus($cancel);
                        break;
                }
                $this->entityManager->flush();
                $success++;

            } catch (\Exception $e) {
                log_error(sprintf("%s: %s", PaymentStatusController::class, $e->getMessage()));
                $errors++;
            }
        }

        if ($success) {
            $this->addSuccess(trans('stripe.admin.payment_status.bulk_action.success', ['%count%' => $success]), 'admin');
        }

        if ($errors) {
            $this->addError(trans('stripe.admin.payment_status.bulk_action.error', ['%count%' => $errors]), 'admin');
        }

        return $this->redirectToRoute('stripe_admin_payment_status_pageno', ['resume' => Constant::ENABLED]);
    }

    /**
     * @param array $searchData
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function createQueryBuilder(array $searchData)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('o')
            ->from(Order::class, 'o')
            ->orderBy('o.order_date', 'DESC')
            ->addOrderBy('o.id', 'DESC');

        if (!empty($searchData['Payments']) && count($searchData['Payments']) > 0) {
            $qb->andWhere($qb->expr()->in('o.Payment', ':Payments'))
                ->setParameter('Payments', $searchData['Payments']);
        }

        if (!empty($searchData['OrderStatuses']) && count($searchData['OrderStatuses']) > 0) {
            $qb->andWhere($qb->expr()->in('o.OrderStatus', ':OrderStatuses'))
                ->setParameter('OrderStatuses', $searchData['OrderStatuses']);
        }

        if (!empty($searchData['PaymentStatuses']) && count($searchData['PaymentStatuses']) > 0) {
            $qb->andWhere($qb->expr()->in('o.StripePaymentStatus', ':PaymentStatuses'))
                ->setParameter('PaymentStatuses', $searchData['PaymentStatuses']);
        }

        return $qb;
    }
}
