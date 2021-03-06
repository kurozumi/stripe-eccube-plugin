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


use Eccube\Controller\AbstractController;
use Eccube\Util\CacheUtil;
use Eccube\Util\StringUtil;
use Plugin\Stripe4\Form\Type\Admin\Stripe\ConfigType;
use Plugin\Stripe4\Form\Type\Admin\Stripe\UserType;
use Plugin\Stripe4\Repository\ConfigRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ConfigController
 * @package Plugin\Stripe4\Controller\Admin
 *
 * @Route("/%eccube_admin_route%/stripe")
 */
class StripeController extends AbstractController
{
    /**
     * @var ConfigRepository
     */
    private $configRepository;

    public function __construct(ConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    /**
     * @param Request $request
     * @param CacheUtil $cacheUtil
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @Route("/user", name="stripe4_admin_stripe_user")
     * @Template("@Stripe4/admin/Stripe/user.twig")
     */
    public function user(Request $request, CacheUtil $cacheUtil)
    {
        $form = $this->createForm(UserType::class, [
            'public_key' => getenv('STRIPE_PUBLIC_KEY'),
            'secret_key' => getenv('STRIPE_SECRET_KEY')
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $this->replaceOrAddEnv([
                'STRIPE_PUBLIC_KEY' => $data['public_key'],
                'STRIPE_SECRET_KEY' => $data['secret_key']
            ]);

            $cacheUtil->clearCache();

            $this->addSuccess('admin.common.save_complete', 'admin');

            return $this->redirectToRoute('stripe4_admin_stripe_config');
        }

        return [
            'form' => $form->createView()
        ];
    }

    /**
     * @param Request $request
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @Route("/config", name="stripe4_admin_stripe_config")
     * @Template("@Stripe4/admin/Stripe/index.twig")
     */
    public function index(Request $request)
    {
        $Config = $this->configRepository->get();
        $form = $this->createForm(ConfigType::class, $Config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $Config = $form->getData();
            $this->entityManager->persist($Config);
            $this->entityManager->flush();

            $this->addSuccess('stripe.admin.save.success', 'admin');

            return $this->redirectToRoute('stripe4_admin_stripe_config');
        }

        return [
            'form' => $form->createView(),
            'public_key' => getenv('STRIPE_PUBLIC_KEY')
        ];
    }

    private function replaceOrAddEnv(array $replacement)
    {
        $envFile = $this->getParameter('kernel.project_dir') . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envFile)) {
            $env = file_get_contents($envFile);
            $env = StringUtil::replaceOrAddEnv($env, $replacement);
            file_put_contents($envFile, $env);
        }
    }
}
