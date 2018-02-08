<?php

namespace Plugin\PayPalExpress\Service;

use Eccube\Application;
use Eccube\Entity\Order;

class PayPalExpressCheckoutNvp extends PayPalExpressCheckoutNvpEx
{

    /**
     * SetExpressCheckout処理
     *
     * @param $param
     * @param Order $Order
     * @return string
     * @throws \Exception
     */
    public function setExpressCheckout($param, Order $Order)
    {

        if (!empty($param) && $param == 'payment') {
            $this->options['RETURNURL'] = $this->app->url('plugin_paypal_express_complete', array('param' => $param));
            // useraction=commmitをつけるとPayPal支払画面で「同意して支払う」ボタンと表示される
            $urlParam = '&useraction=commit&token=';
            $this->options['NOSHIPPING'] = 1;
            $this->options['ADDROVERRIDE'] = 1;

            // 配列の最初のお届け先情報を設定
            $shippings = $Order->getShippings();
            /** @var \Eccube\Entity\Shipping $Shipping */
            $Shipping = $shippings[0];

            $this->options['PAYMENTREQUEST_0_SHIPTONAME'] = $Shipping->getName02() . ' ' . $Shipping->getName01();
            $this->options['PAYMENTREQUEST_0_SHIPTOZIP'] = $Shipping->getZip01() . '-' . $Shipping->getZip02();
            $this->options['PAYMENTREQUEST_0_SHIPTOSTATE'] = $Shipping->getPref()->getName();
            $this->options['PAYMENTREQUEST_0_SHIPTOCITY'] = $Shipping->getAddr01();
            $this->options['PAYMENTREQUEST_0_SHIPTOSTREET'] = $Shipping->getAddr02();
            $this->options['PAYMENTREQUEST_0_SHIPTOSTREET2'] = '';
            $this->options['EMAIL'] = $Order->getEmail();
            $this->options['PAYMENTREQUEST_0_SHIPTOPHONENUM'] = $Shipping->getTel01() . '-' . $Shipping->getTel02() . '-' . $Shipping->getTel03();

        } else {
            // ショートカットの場合、設定する値を変更させる
            $this->options['RETURNURL'] = $this->app->url('plugin_paypal_express_confirm');
            // 確認画面へ遷移させる必要があるため、PayPal支払画面では「同意して支払う」ボタンと表示させない
            $urlParam = '&token=';
            $this->options['NOTETOBUYER'] = $this->app['config']['paypal_express_notetobuyer'];
            $this->options['TOTALTYPE'] = 'EstimatedTotal';

            // ログインしていた場合はPayPal会員登録の初期値を設定
            if ($this->app->isGranted('ROLE_USER')) {
                // 会員の場合、会員住所を設定
                $Customer = $this->app->user();
                $addr = $Customer->getCustomerAddresses();
                /** @var \Eccube\Entity\CustomerAddress $CustomerAddress */
                $CustomerAddress = $addr[0];
                $this->options['ADDROVERRIDE'] = '1';
                $this->options['PAYMENTREQUEST_0_SHIPTONAME'] = $CustomerAddress->getName02() . ' ' . $CustomerAddress->getName01();
                $this->options['PAYMENTREQUEST_0_SHIPTOZIP'] = $CustomerAddress->getZip01() . '-' . $CustomerAddress->getZip02();
                $this->options['PAYMENTREQUEST_0_SHIPTOSTATE'] = $CustomerAddress->getPref()->getName();
                $this->options['PAYMENTREQUEST_0_SHIPTOCITY'] = $CustomerAddress->getAddr01();
                $this->options['PAYMENTREQUEST_0_SHIPTOSTREET'] = $CustomerAddress->getAddr02();
                $this->options['PAYMENTREQUEST_0_SHIPTOSTREET2'] = '';
                $this->options['EMAIL'] = $Customer->getEmail();
                $this->options['PAYMENTREQUEST_0_SHIPTOPHONENUM'] = $CustomerAddress->getTel01() . '-' . $CustomerAddress->getTel02() . '-' . $CustomerAddress->getTel03();
            }

        }
        $logo = $this->payPalExpress->getCorporateLogo();
        if (!empty($logo)) {
            $this->options['LOGOIMG'] = $this->app['request']->getSchemeAndHttpHost() . $this->app['request']->getBasePath() . $this->app['config']['paypal_img_urlpath'] . '/' . $logo;
        }
        $this->options['CARTBORDERCOLOR'] = str_replace('#', '', $this->payPalExpress->getBorderColor());
        $this->options['CANCELURL'] = $this->app->url('plugin_paypal_express_cancel');
        $this->options['METHOD'] = self::PAYPAL_METHOD_SET_EXPRESS_CHECKOUT;
        $this->options['PAYMENTREQUEST_0_INVNUM'] = $Order->getId();
        $this->options['PAYMENTREQUEST_0_AMT'] = $Order->getTotal();
        $this->options['PAYMENTREQUEST_0_SHIPPINGAMT'] = $Order->getDeliveryFeeTotal();
        // 国コードをセット(デフォルトJP)
        $this->options['PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE'] = $this->app['config']['paypal_express_country_code'];

        // 商品
        $this->setPaymentRequestItem($Order);


        $this->logger->addInfo(self::PAYPAL_METHOD_SET_EXPRESS_CHECKOUT . ' : Send', $this->options);

        $response = $this->getPayPalExpressCheckoutNvp($this->sendPayPalExpressCheckoutNvp($this->options));

        $this->logger->addInfo(self::PAYPAL_METHOD_SET_EXPRESS_CHECKOUT . ' : Receive', $response);

        return $this->payPalUrl . $urlParam . $response['TOKEN'];
    }

    /**
     * GetExpressCheckoutDetails処理
     *
     * @param $token
     * @param $payerId
     * @param Order $Order
     * @return array
     * @throws \Exception
     */
    public function getExpressCheckoutDetails($token, $payerId, Order $Order)
    {

        $this->options['METHOD'] = self::PAYPAL_METHOD_GET_EXPRESS_CHECKOUT_DETAILS;
        $this->options['TOKEN'] = $token;
        $this->options['PAYMENTREQUEST_0_INVNUM'] = $Order->getId();

        $this->logger->addInfo(self::PAYPAL_METHOD_GET_EXPRESS_CHECKOUT_DETAILS . ' : Send', $this->options);

        $response = $this->getPaypalExpressCheckoutNvp($this->sendPayPalExpressCheckoutNvp($this->options));

        $this->logger->addInfo(self::PAYPAL_METHOD_GET_EXPRESS_CHECKOUT_DETAILS . ' : Receive', $response);

        $this->session->set('PAYPAL_TOKEN', $token);
        $this->session->set('PAYPAL_PAYERID', $payerId);

        return $response;

    }

    /**
     * DoExpressCheckoutPayment処理
     *
     * @param Order $Order
     * @throws \Exception
     * @throws \Plugin\PayPalExpress\Exception\PayPalExpressPdrException
     */
    public function doExpressCheckoutPayment(Order $Order)
    {

        $this->options['METHOD'] = self::PAYPAL_METHOD_DO_EXPRESS_CHECKOUT_PAYMENT;
        $this->options['TOKEN'] = $this->session->get('PAYPAL_TOKEN');
        $this->options['PAYERID'] = $this->session->get('PAYPAL_PAYERID');
        $this->options['PAYMENTREQUEST_0_INVNUM'] = $Order->getId();
        $this->options['PAYMENTREQUEST_0_AMT'] = $Order->getTotal();
        $this->options['BUTTONSOURCE'] = $this->app['config']['paypal_express_buttonsource'];

        $this->logger->addInfo(self::PAYPAL_METHOD_DO_EXPRESS_CHECKOUT_PAYMENT . ' : Send', $this->options);

        $response = $this->getPayPalExpressCheckoutNvp($this->sendPayPalExpressCheckoutNvp($this->options));

        $this->logger->addInfo(self::PAYPAL_METHOD_DO_EXPRESS_CHECKOUT_PAYMENT . ' : Receive', $response);

        $this->logger->addInfo('complete');

        $this->session->remove('PAYPAL_TOKEN');
        $this->session->remove('PAYPAL_PAYERID');

    }

    /**
     * 受注商品をパラメータにセット
     *
     * @param Order $Order
     */
    private function setPaymentRequestItem(Order $Order)
    {
        $i = 0;
        $total = 0;

        /** @var \Eccube\Entity\OrderDetail $OrderDetail */
        foreach ($Order->getOrderDetails() as $OrderDetail) {
            $productCode = $OrderDetail->getProductCode();
            if (!is_null($productCode)) {
                $this->options['L_PAYMENTREQUEST_0_NUMBER' . $i] = $productCode;
            }

            $name = $OrderDetail->getProductName();
            $ClassCategory1 = $OrderDetail->getClassCategoryName1();
            if (!is_null($ClassCategory1)) {
                $name .= "/" . $ClassCategory1;
            }
            $ClassCategory2 = $OrderDetail->getClassCategoryName2();
            if (!is_null($ClassCategory2)) {
                $name .= "/" . $ClassCategory2;
            }
            $this->options['L_PAYMENTREQUEST_0_DESC' . $i] = $name;

            $this->options['L_PAYMENTREQUEST_0_QTY' . $i] = $OrderDetail->getQuantity();
            $this->options['L_PAYMENTREQUEST_0_AMT' . $i] = $OrderDetail->getPriceIncTax();
            $total += $this->options['L_PAYMENTREQUEST_0_AMT' . $i] * $OrderDetail->getQuantity();
            $i++;
        }

        // 手数料
        if ($Order->getCharge() > 0) {
            $this->options['L_PAYMENTREQUEST_0_DESC' . $i] = '手数料';
            $this->options['L_PAYMENTREQUEST_0_QTY' . $i] = 1;
            $this->options['L_PAYMENTREQUEST_0_AMT' . $i] = $Order->getCharge();
            $total += $this->options['L_PAYMENTREQUEST_0_AMT' . $i];
            $i++;
        }

        // 値引き
        if ($Order->getDiscount() > 0) {
            $this->options['L_PAYMENTREQUEST_0_DESC' . $i] = '値引き';
            $this->options['L_PAYMENTREQUEST_0_QTY' . $i] = 1;
            $this->options['L_PAYMENTREQUEST_0_AMT' . $i] = 0 - $Order->getDiscount();
            $total += $this->options['L_PAYMENTREQUEST_0_AMT' . $i];
            $i++;
        }

        // TODO ポイント値引き

        $this->options['PAYMENTREQUEST_0_ITEMAMT'] = $total;

    }

}
