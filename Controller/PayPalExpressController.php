<?php
namespace Plugin\PayPalExpress\Controller;

use Eccube\Application;
use Eccube\Common\Constant;
use Eccube\Entity\Customer;
use Eccube\Entity\MailHistory;
use Eccube\Entity\Order;
use Eccube\Util\Str;
use Plugin\PayPalExpress\Exception\PayPalExpressPdrException;
use Plugin\PayPalExpress\Service\PayPalExpressCheckoutNvp;
use Symfony\Component\HttpFoundation\Request;

class PayPalExpressController
{

    /**
     * @var string 受注IDキー
     */
    private $sessionOrderKey = 'eccube.front.shopping.order.id';

    /**
     * PayPalログイン画面へ遷移(購入フロー)
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function checkout(Application $app, Request $request)
    {

        // カートチェック
        if (count($app['eccube.service.cart']->getCart()->getCartItems()) <= 0) {
            // カートが存在しない時はエラー
            return $app->redirect($app->url('cart'));
        }

        // 既にデータは作成されているため、セッションに保持されている情報から受注情報を取得
        $Order = $app['eccube.plugin.service.paypal_express']->getOrder();

        $app['eccube.plugin.service.paypal_express']->setOrderUpdate($Order, $_POST['shopping']);

        try {

            if (!$app['eccube.plugin.service.paypal_express']->isOrderSetting($Order)) {
                // 在庫チェックなどでエラー
                return $app->redirect($app->url('shopping_error'));
            }

            return $this->expressCheckoutRedirect($app, $Order, $request->get('param'));

        } catch (\Exception $e) {
            $app->addRequestError($e->getMessage());

            return $app->redirect($app->url('plugin_paypal_express_cancel'));
        }

    }


    /**
     * PayPalログイン画面へ遷移(ショートカット用)
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function shortcutCheckout(Application $app, Request $request)
    {
        $cartService = $app['eccube.service.cart'];

        // カートチェック
        if (count($cartService->getCart()->getCartItems()) <= 0) {
            // カートが存在しない時はエラー
            return $app->redirect($app->url('cart'));
        }

        // 未ログインの場合は, ログイン画面へリダイレクト.
        if ($app->isGranted('ROLE_USER')) {
            $Customer = $app->user();
        } else {
            // 非会員でも一度会員登録されていればその情報を使う
            $arr = $app['session']->get('eccube.front.shopping.nonmember');
            if (is_null($arr) || (!array_key_exists('customer', $arr) || !array_key_exists('pref', $arr))) {
                $Customer = new Customer();
                // ダミーの都道府県をセット
                $Customer->setPref($app['eccube.repository.master.pref']->find(13));
            } else {
                $Customer = $arr['customer'];
                $Customer->setPref($app['eccube.repository.master.pref']->find($arr['pref']));
            }
        }

        // ランダムなpre_order_idを作成
        $preOrderId = sha1(Str::random(32));

        try {
            // 受注情報を作成
            $Order = $app['eccube.service.order']->registerPreOrderFromCartItems($cartService->getCart()->getCartItems(), $Customer,
                $preOrderId);
        } catch (\Eccube\Exception\CartException $e) {
            $app->addRequestError($e->getMessage());
            return $app->redirect($app->url('cart'));
        } catch (\Exception $e) {
            $app->addRequestError('購入できない商品が含まれております。恐れ入りますがお問い合わせページよりお問い合わせください。');
            return $app->redirect($app->url('cart'));
        }

        if (version_compare(\Eccube\Common\Constant::VERSION, '3.0.4', '<=')) {
            $cartService->setPreOrderId($preOrderId);
            $cartService->save();
        }

        // 受注関連情報を最新状態に更新
        $app['orm.em']->refresh($Order);

        /** @var \Plugin\PayPalExpress\Entity\PayPalExpress $PayPalExpress */
        $PayPalExpress = $app['eccube.plugin.repository.paypal_express']->find(1);

        $deliveries = $app['eccube.plugin.service.paypal_express']->findDeliveriesFromOrderDetails($app, $Order->getOrderDetails(), $PayPalExpress->getPaymentId());

        /** @var \Eccube\Entity\Payment $Payment */
        $Payment = $app['eccube.repository.payment']->find($PayPalExpress->getPaymentId());
        if (!$Payment) {
            $app->addRequestError('paypal_express_checkout.payment.error');

            return $app->redirect($app->url('cart'));
        }

        $Order->setPayment($Payment);
        $Order->setPaymentMethod($Payment->getMethod());

        $Order->setCharge($Payment->getCharge());

        $Order->setTotal($Order->getTotal() + $Order->getCharge());
        $Order->setPaymentTotal($Order->getTotal());

        $app['orm.em']->flush($Order);

        if (count($deliveries) == 0) {
            $app->addRequestError('paypal_express_checkout.delivery.error');

            return $app->redirect($app->url('cart'));

        } else if (count($deliveries) > 1) {
            // 配送先が複数ある場合、配送方法選択画面を表示

            // 配送業者
            $builder = $app['form.factory']->createBuilder('paypalexpress_delivery', null, array(
                'deliveries' => $deliveries,
            ));
            $form = $builder->getForm();

            return $app->render('PayPalExpress/Resource/template/delivery.twig', array(
                'form' => $form->createView(),
                'Order' => $Order,
                'PayPalExpress' => $PayPalExpress,
            ));

        } else {
            // 配送業者が1つしかない場合、PayPal画面を表示

            try {

                if (!$app['eccube.plugin.service.paypal_express']->isOrderSetting($Order)) {
                    // 在庫チェックなどでエラー
                    return $app->redirect($app->url('shopping_error'));
                }

                return $this->expressCheckoutRedirect($app, $Order, $request->get('param'));

            } catch (\Exception $e) {
                $app->addRequestError($e->getMessage());

                return $app->redirect($app->url('plugin_paypal_express_cancel'));
            }
        }

    }

    /**
     * 配送業者選択処理
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function delivery(Application $app, Request $request)
    {

        // カートチェック
        if (count($app['eccube.service.cart']->getCart()->getCartItems()) <= 0) {
            // カートが存在しない時はエラー
            return $app->redirect($app->url('cart'));
        }

        /** @var \Eccube\Entity\Order $Order */
        $Order = $app['eccube.plugin.service.paypal_express']->getOrder();

        $PayPalExpress = $app['eccube.plugin.repository.paypal_express']->find(1);

        $deliveries = $app['eccube.plugin.service.paypal_express']->findDeliveriesFromOrderDetails($app, $Order->getOrderDetails(), $PayPalExpress->getPaymentId());

        $builder = $app['form.factory']->createBuilder('paypalexpress_delivery', null, array(
            'deliveries' => $deliveries,
        ));
        $form = $builder->getForm();

        if ('POST' === $request->getMethod()) {

            $form->handleRequest($request);

            if ($form->isValid()) {

                $data = $form->getData();
                /** @var \Eccube\Entity\Delivery $Delivery */
                $Delivery = $data['delivery'];

                $shippings = $Order->getShippings();

                /** @var \Eccube\Entity\Shipping $Shipping */
                foreach ($shippings as $Shipping) {
                    // 複数配送時の考慮
                    $deliveryFee = $app['eccube.repository.delivery_fee']->findOneBy(array(
                        'Delivery' => $Delivery,
                        'Pref' => $Shipping->getPref()
                    ));

                    $Shipping->setDelivery($Delivery);
                    $Shipping->setDeliveryFee($deliveryFee);
                    $Shipping->setShippingDeliveryFee($deliveryFee->getFee());
                    $Shipping->setShippingDeliveryName($Delivery->getName());
                }

                // 支払い情報をセット
                $Order->setDeliveryFeeTotal($app['eccube.plugin.service.paypal_express']->getShippingDeliveryFeeTotal($shippings));

                $total = $Order->getSubTotal() + $Order->getCharge() + $Order->getDeliveryFeeTotal();

                $Order->setTotal($total);
                $Order->setPaymentTotal($total);

                // 受注関連情報を最新状態に更新
                $app['orm.em']->refresh($Order);
                $app['orm.em']->flush();

                $Order = $app['eccube.service.order']->getAmount($Order, $app['eccube.service.cart']->getCart());

                try {

                    if (!$app['eccube.plugin.service.paypal_express']->isOrderSetting($Order)) {
                        // 在庫チェックなどでエラー
                        return $app->redirect($app->url('shopping_error'));
                    }

                    return $this->expressCheckoutRedirect($app, $Order, $request->get('param'));

                } catch (\Exception $e) {
                    $app->addRequestError($e->getMessage());

                    return $app->redirect($app->url('plugin_paypal_express_cancel'));
                }

            }
        }

        return $app->render('PayPalExpress/Resource/template/delivery.twig', array(
            'form' => $form->createView(),
            'Order' => $Order,
            'PayPalExpress' => $PayPalExpress,
        ));

    }


    /**
     * PayPalからリダイレクト時の処理
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function confirm(Application $app, Request $request)
    {

        /** @var \Plugin\PayPalExpress\Entity\PayPalExpress $PayPalExpress */
        $PayPalExpress = $app['eccube.plugin.repository.paypal_express']->find(1);

        $expressCheckoutNvp = new PayPalExpressCheckoutNvp($app, $PayPalExpress);

        /** @var \Eccube\Entity\Order $Order */
        $Order = $app['eccube.plugin.service.paypal_express']->getOrder();

        try {
            $response = $expressCheckoutNvp->getExpressCheckoutDetails($request->get('token'), $request->get('PayerID'), $Order);

            // paypalから取得した情報をセット

            if ($app->isGranted('ROLE_USER')) {
            } else {
                $Order->setName01($response['LASTNAME']);
                $Order->setName02($response['FIRSTNAME']);
                $Order->setEmail($response['EMAIL']);

                // カナは取得できないのでダミーデータを入れる
                $Order->setKana01('ー');
                $Order->setKana02('ー');

                // 電話番号を4文字づつに分割
                if (isset($response['PHONENUM']) && Str::isNotBlank($response['PHONENUM'])) {
                    $tel = preg_replace('/[^0-9]/', '', $response['PHONENUM']);
                    $arrTel = str_split($tel, 4);
                    $i = 1;
                    $Order->setTel03('');
                    foreach ($arrTel as $num) {
                        if ($i <= 2) {
                            if ($i == 1) {
                                $Order->setTel01($num);
                            } else if ($i == 2) {
                                $Order->setTel02($num);
                            }
                        } else {
                            $Order->setTel03($num);
                        }
                        $i++;
                    }
                }
            }

            $Order->setAddr01($response['SHIPTOCITY']);
            $street2 = isset($response['SHIPTOSTREET2']) ? ' ' . $response['SHIPTOSTREET2'] : '';
            $Order->setAddr02($response['SHIPTOSTREET'] . $street2);

            try {
                $pref = $app['eccube.plugin.service.paypal_express']->getPrefId($response['PAYMENTREQUEST_0_SHIPTOSTATE']);
            } catch (\Exception $e) {

                $app->addRequestError($e->getMessage());
            }
            if (!empty($pref)) {
                $Pref = $app['eccube.repository.master.pref']->find($pref);
                $Order->setPref($Pref);
            }
            $arrZip = explode('-', $response['SHIPTOZIP']);

            $Order->setZipcode($response['SHIPTOZIP']);
            if (count($arrZip) > 1) {
                $Order->setZip01($arrZip[0]);
                $Order->setZip02($arrZip[1]);
            } else {
                $Order->setZip01($arrZip[0]);
            }


            $note = isset($response['PAYMENTREQUEST_0_NOTETEXT']) ? ' ' . $response['PAYMENTREQUEST_0_NOTETEXT'] : '';
            if (!empty($note)) {
                $Order->setMessage($note);
            }

            // お届け先情報を設定
            $shippings = $Order->getShippings();

            /** @var \Eccube\Entity\Shipping $Shipping */
            foreach ($shippings as $Shipping) {
                // 複数配送時の考慮
                $Shipping->setName01($response['PAYMENTREQUEST_0_SHIPTONAME']);
                $Shipping->setAddr01($response['PAYMENTREQUEST_0_SHIPTOCITY']);
                $street2 = isset($response['PAYMENTREQUEST_0_SHIPTOSTREET2']) ? ' ' . $response['PAYMENTREQUEST_0_SHIPTOSTREET2'] : '';
                $Shipping->setAddr02($response['PAYMENTREQUEST_0_SHIPTOSTREET'] . $street2);

                try {
                    $pref = $app['eccube.plugin.service.paypal_express']->getPrefId($response['PAYMENTREQUEST_0_SHIPTOSTATE']);
                } catch (\Exception $e) {
                    $app->addRequestError($e->getMessage());
                }
                if (!empty($pref)) {
                    $Pref = $app['eccube.repository.master.pref']->find($pref);
                    $Shipping->setPref($Pref);
                }

                $arrZip = explode('-', $response['PAYMENTREQUEST_0_SHIPTOZIP']);
                $Shipping->setZipcode($response['PAYMENTREQUEST_0_SHIPTOZIP']);
                if (count($arrZip) > 1) {
                    $Shipping->setZip01($arrZip[0]);
                    $Shipping->setZip02($arrZip[1]);
                } else {
                    $Shipping->setZip01($arrZip[0]);
                }

                $Delivery = $Shipping->getDelivery();

                $deliveryFee = $app['eccube.repository.delivery_fee']->findOneBy(array(
                    'Delivery' => $Delivery,
                    'Pref' => $Shipping->getPref()
                ));

                $Shipping->setDeliveryFee($deliveryFee);
                $Shipping->setShippingDeliveryFee($deliveryFee->getFee());
                $Shipping->setShippingDeliveryName($Delivery->getName());
                $app['orm.em']->flush($Shipping);
            }

            // 支払い情報をセット
            $Order->setDeliveryFeeTotal($app['eccube.plugin.service.paypal_express']->getShippingDeliveryFeeTotal($shippings));

            $total = $Order->getSubTotal() + $Order->getCharge() + $Order->getDeliveryFeeTotal();

            $Order->setTotal($total);
            $Order->setPaymentTotal($total);

            $app['orm.em']->flush($Order);

            return $app->render('PayPalExpress/Resource/template/confirm.twig', array(
                'Order' => $Order,
            ));
        } catch (\Exception $e) {
            $app->addRequestError($e->getMessage());

            return $app->redirect($app->url('plugin_paypal_express_cancel'));
        }

    }


    /**
     * PayPal購入完了処理
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function complete(Application $app, Request $request)
    {

        /** @var \Plugin\PayPalExpress\Entity\PayPalExpress $PayPalExpress */
        $PayPalExpress = $app['eccube.plugin.repository.paypal_express']->find(1);

        /** @var \Eccube\Entity\Order $Order */
        $Order = $app['eccube.plugin.service.paypal_express']->getOrder();

        $expressCheckoutNvp = new PayPalExpressCheckoutNvp($app, $PayPalExpress);

        $param = $request->get('param');

        try {

            if (!empty($param) && $param == 'payment') {
                // 支払選択から遷移した場合、getExpressCheckoutDetailsを行う
                $expressCheckoutNvp->getExpressCheckoutDetails($request->get('token'), $request->get('PayerID'), $Order);
            }

            $expressCheckoutNvp->doExpressCheckoutPayment($Order);

            if ($app->isGranted('ROLE_USER')) {
                // 会員の場合、購入金額を更新
                $app['eccube.service.order']->setCustomerUpdate($app['orm.em'], $Order, $app->user());
            }

            // 受注情報を更新
            $Order->setOrderDate(new \DateTime());
            // 入金済みに変更
            $Order->setOrderStatus($app['eccube.repository.order_status']->find($app['config']['order_pre_end']));

            $app['orm.em']->flush($Order);

            // カート削除
            $app['eccube.service.cart']->clear()->save();

            // 受注IDをセッションにセット
            $app['session']->set($this->sessionOrderKey, $Order->getId());

            if (version_compare('3.0.10', Constant::VERSION, '<=')) {
                // 3.0.10以降の対応

                $app['eccube.service.shopping']->notifyComplete($Order);
                $app['eccube.service.shopping']->sendOrderMail($Order);

            } else{
                // メール送信
                $app['eccube.service.mail']->sendOrderMail($Order);

                // 送信履歴を保存.
                $MailTemplate = $app['eccube.repository.mail_template']->find(1);

                $body = $app->renderView($MailTemplate->getFileName(), array(
                    'header' => $MailTemplate->getHeader(),
                    'footer' => $MailTemplate->getFooter(),
                    'Order' => $Order,
                ));

                $MailHistory = new MailHistory();
                $MailHistory
                    ->setSubject('[' . $app['eccube.repository.base_info']->get()->getShopName() . '] ' . $MailTemplate->getSubject())
                    ->setMailBody($body)
                    ->setMailTemplate($MailTemplate)
                    ->setSendDate(new \DateTime())
                    ->setOrder($Order);
                $app['orm.em']->persist($MailHistory);
                $app['orm.em']->flush($MailHistory);

            }

            return $app->redirect($app->url('shopping_complete'));

        } catch (PayPalExpressPdrException $e) {
            // PayPal画面を再表示
            $url = $expressCheckoutNvp->setExpressCheckout($param, $Order);

            return $app->redirect($url);

        } catch (\Exception $e) {
            $app->addRequestError($e->getMessage());

            return $app->redirect($app->url('plugin_paypal_express_cancel'));
        }

    }

    /**
     * PayPalキャンセル処理
     * PayPal決済画面からキャンセルされたら在庫を元に戻す
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function cancel(Application $app, Request $request)
    {

        /** @var \Eccube\Entity\Order $Order */
        $Order = $app['eccube.plugin.service.paypal_express']->getOrder();

        if ($Order) {
            // 在庫を元に戻す
            $app['eccube.plugin.service.paypal_express']->undoStock($Order);
        }

        return $app->redirect($app->url('cart'));

    }

    /**
     * SetExpressCheckoutのリダイレクト処理
     *
     * @param $app
     * @param Order $Order
     * @param $param
     * @return mixed
     */
    private function expressCheckoutRedirect($app, Order $Order, $param)
    {

        $PayPalExpress = $app['eccube.plugin.repository.paypal_express']->find(1);

        $expressCheckoutNvp = new PayPalExpressCheckoutNvp($app, $PayPalExpress);

        $url = $expressCheckoutNvp->setExpressCheckout($param, $Order);

        return $app->redirect($url);

    }

}
