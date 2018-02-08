<?php
namespace Plugin\PayPalExpress\Controller;

use Eccube\Application;
use Eccube\Common\Constant;
use Eccube\Util\Cache;
use Plugin\PayPalExpress\Entity\PayPalExpress;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;

class ConfigController
{

    /**
     * PayPal用設定画面
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index(Application $app, Request $request)
    {

        $PayPalExpress = $app['eccube.plugin.repository.paypal_express']->find(1);

        if ($PayPalExpress) {
            $previousPassword = $PayPalExpress->getApiPassword();
            if (!empty($previousPassword)) {
                $PayPalExpress->setApiPassword($app['config']['default_password']);
            }
        } else {
            $PayPalExpress = new PayPalExpress();
        }

        $form = $app['form.factory']->createBuilder('paypalexpress_config', $PayPalExpress)->getForm();

        $logo = '';
        if (!is_null($PayPalExpress->getPaypalLogo())) {
            $logo = $PayPalExpress->getPaypalLogo();
        }

        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);

            if ($form->isValid()) {

                /** @var \Plugin\PayPalExpress\Entity\PayPalExpress $PayPalExpress */
                $PayPalExpress = $form->getData();

                // ロゴ画像
                $formFile = $form['logo']->getData();
                if (!empty($formFile)) {
                    $fileName = 'corporate_logo.' . $formFile->getClientOriginalExtension();
                    $formFile->move($app['config']['root_dir'] . '/' . $app['config']['paypal_img_realdir'], $fileName);
                    $PayPalExpress->setCorporateLogo($fileName);
                }

                // IDは1固定
                $PayPalExpress->setId(1);

                if ($PayPalExpress->getApiPassword() === $app['config']['default_password']) {
                    $PayPalExpress->setApiPassword($previousPassword);
                }

                // PayPalロゴ画像の設定
                $paypalLogo = '';
                if (!is_null($PayPalExpress->getPaypalLogo())) {
                    $paypalLogo = $app['config']['paypal_express_paypal_logo_' . $PayPalExpress->getPaypalLogo()];
                }
                $fs = new Filesystem();
                $filePath = $app['config']['plugin_realdir'] . '/PayPalExpress/Resource/template/Block/paypal_logo.twig';
                if ($logo != $PayPalExpress->getPaypalLogo()) {
                    $fs->dumpFile($filePath, $paypalLogo);
                    Cache::clear($app, false);
                }
                // 完了メール送信
                if ($PayPalExpress->getIsConfigured() != Constant::ENABLED) {
                    // 初めて設定を行ったユーザのみ送信する. 1: 送信済みをセット
                    $PayPalExpress->setIsConfigured(Constant::ENABLED);

                    // メール送信
                    $app['eccube.plugin.service.paypal_express']->sendConfigCompleteMail();
                }

                $app['orm.em']->persist($PayPalExpress);
                $app['orm.em']->flush();


                // $app->addSuccess('admin.paypal_express.save.complete', 'admin');

                return $app->redirect($app->url('plugin_PayPalExpress_config_complete'));

            }

        }

        return $app->render('PayPalExpress/Resource/template/admin/config.twig', array(
            'form' => $form->createView(),
            'PayPalExpress' => $PayPalExpress,
        ));
    }

    /**
     * PayPal設定完了画面
     *
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function complete(Application $app)
    {
        return $app->render('PayPalExpress/Resource/template/admin/config_complete.twig');
    }

}
