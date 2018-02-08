<?php

namespace Plugin\PayPalExpress\Service;

use Eccube\Application;
use Eccube\Common\Constant;
use Eccube\Entity\Order;
use Eccube\Util\Str;

class PayPalExpressService
{
    /** @var \Eccube\Application */
    public $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * PayPal設定画面で初めて設定を行ったユーザにメール送信する
     */
    public function sendConfigCompleteMail()
    {

        $body = $this->app->renderView('PayPalExpress/Resource/template/admin/config_complete_mail.twig', array());

        $BaseInfo = $this->app['eccube.repository.base_info']->get();

        $message = \Swift_Message::newInstance()
            ->setSubject('【日本発!ECオープンプラットフォーム EC-CUBE】モジュールダウンロードありがとうございます')
            ->setFrom(array($BaseInfo->getEmail01() => $BaseInfo->getShopName()))
            ->setTo($BaseInfo->getEmail01())
            ->setBcc($BaseInfo->getEmail01())
            ->setReplyTo($BaseInfo->getEmail03())
            ->setReturnPath($BaseInfo->getEmail04())
            ->setBody($body);

        $this->app->mail($message);

    }

    /**
     * セッションにセットされたカートIDを元に受注情報を取得
     *
     * @return null|object
     */
    public function getOrder()
    {

        // 受注データを取得
        $Order = $this->app['eccube.repository.order']->findOneBy(array(
            'pre_order_id' => $this->app['eccube.service.cart']->getPreOrderId(),
        ));

        return $Order;

    }


    /**
     * 配送業者を取得
     *
     * @param $app
     * @param $details
     * @param $paymentId
     * @return mixed
     */
    public function findDeliveriesFromOrderDetails($app, $details, $paymentId)
    {

        $productTypes = array();
        foreach ($details as $detail) {
            $productTypes[] = $detail->getProductClass()->getProductType();
        }

        // PayPalで配送可能な配送業者を取得
        $qb = $app['orm.em']->createQueryBuilder();
        $deliveries = $qb->select('d')
            ->from('\Eccube\Entity\Delivery', 'd')
            ->innerJoin('Eccube\Entity\PaymentOption', 'po', 'WITH', 'po.delivery_id = d.id')
            ->innerJoin('Eccube\Entity\Payment', 'p', 'WITH', 'p.id = po.payment_id')
            ->where('d.ProductType in (:productTypes)')
            ->andWhere('p.id = :paymentId')
            ->setParameter('productTypes', $productTypes)
            ->setParameter('paymentId', $paymentId)
            ->orderBy("d.rank", "ASC")
            ->getQuery()
            ->getResult();

        return $deliveries;

    }


    /**
     * 在庫情報更新
     *
     * @param Order $Order
     * @return bool
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function isOrderSetting(Order $Order)
    {

        // トランザクション制御
        $em = $this->app['orm.em'];
        $em->getConnection()->beginTransaction();
        try {
            // 商品公開ステータスチェック、商品制限数チェック、在庫チェック
            $check = $this->app['eccube.service.order']->isOrderProduct($em, $Order);
            if (!$check) {
                $em->getConnection()->rollback();
                $em->close();

                return false;
            }

            // 在庫情報を更新
            $this->app['eccube.service.order']->setStockUpdate($em, $Order);

            $em->getConnection()->commit();
            $em->flush();
            $em->close();

        } catch (\Exception $e) {
            $em->getConnection()->rollback();
            $em->close();

            return false;
        }

        return true;

    }

    /**
     * 受注情報、お届け先情報の更新
     *
     * @param Order $Order
     * @param array $formData
     */
    public function setOrderUpdate(Order $Order, array $formData)
    {

        if (!is_null($formData)) {
            $Order->setMessage(Str::ellipsis($formData['message'], 2000, ''));
            // お届け先情報を更新
            $shippings = $Order->getShippings();
            /** @var \Eccube\Entity\Shipping $Shipping */
            foreach ($shippings as $Shipping) {
                $Delivery = $Shipping->getDelivery();
                $Shipping->setShippingDeliveryName($Delivery->getName());
                if (!empty($formData['deliveryTime'])) {
                    $DeliveryTime = $this->app['eccube.repository.delivery_time']->find($formData['deliveryTime']);
                    if ($DeliveryTime) {
                        $Shipping->setShippingDeliveryTime($DeliveryTime->getDeliveryTime());
                    }
                }
                if (!empty($formData['deliveryDate'])) {
                    try {
                        $Shipping->setShippingDeliveryDate(new \DateTime($formData['deliveryDate']));
                    } catch (\Exception $e) {
                        // noop
                    }
                }
                $Shipping->setShippingDeliveryFee($Shipping->getDeliveryFee()->getFee());
                $this->app['orm.em']->flush($Shipping);
            }
        }

        $this->app['orm.em']->flush($Order);

    }


    /**
     * お届け先ごとの送料合計を取得
     *
     * @param $shippings
     * @return int
     */
    public function getShippingDeliveryFeeTotal($shippings)
    {
        $deliveryFeeTotal = 0;
        foreach ($shippings as $Shipping) {
            $deliveryFeeTotal += $Shipping->getShippingDeliveryFee();
        }

        return $deliveryFeeTotal;

    }

    /**
     * 在庫を元に戻す
     *
     * @param Order $Order
     * @return bool
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function undoStock(Order $Order)
    {

        $orderDetails = $Order->getOrderDetails();

        // 在庫情報更新
        foreach ($orderDetails as $orderDetail) {
            // 在庫が無制限かチェックし、制限ありなら在庫数を更新
            if ($orderDetail->getProductClass()->getStockUnlimited() == Constant::DISABLED) {

                $productStock = $this->app['eccube.repository.product_stock']->find(
                    $orderDetail->getProductClass()->getProductStock()->getId()
                );

                // 在庫情報の在庫数を更新
                $stock = $productStock->getStock() + $orderDetail->getQuantity();
                $productStock->setStock($stock);

                // 商品規格情報の在庫数を更新
                $orderDetail->getProductClass()->setStock($stock);
                $this->app['orm.em']->flush();

            }
        }


    }

    /**
     * PayPalで定義されている都道府県を変換
     *
     * @param $prefName
     * @return mixed
     */
    public function getPrefId($prefName)
    {
        $arrPref = array('Hokkaido' => 1,
            '北海道' => 1,
            'Aomori' => 2,
            '青森県' => 2,
            'Iwate' => 3,
            '岩手県' => 3,
            'Miyagi' => 4,
            '宮城県' => 4,
            'Akita' => 5,
            '秋田県' => 5,
            'Yamagata' => 6,
            '山形県' => 6,
            'Fukushima' => 7,
            '福島県' => 7,
            'Ibaraki' => 8,
            '茨城県' => 8,
            'Tochigi' => 9,
            '栃木県' => 9,
            'Gunma' => 10,
            '群馬県' => 10,
            'Saitama' => 11,
            '埼玉県' => 11,
            'Chiba' => 12,
            '千葉県' => 12,
            'Tokyo' => 13,
            '東京都' => 13,
            'Kanagawa' => 14,
            '神奈川県' => 14,
            'Niigata' => 15,
            '新潟県' => 15,
            'Toyama' => 16,
            '富山県' => 16,
            'Ishikawa' => 17,
            '石川県' => 17,
            'Fukui' => 18,
            '福井県' => 18,
            'Yamanashi' => 19,
            '山梨県' => 19,
            'Nagano' => 20,
            '長野県' => 20,
            'Gifu' => 21,
            '岐阜県' => 21,
            'Shizuoka' => 22,
            '静岡県' => 22,
            'Aichi' => 23,
            '愛知県' => 23,
            'Mie' => 24,
            '三重県' => 24,
            'Shiga' => 25,
            '滋賀県' => 25,
            'Kyoto' => 26,
            '京都府' => 26,
            'Osaka' => 27,
            '大阪府' => 27,
            'Hyogo' => 28,
            '兵庫県' => 28,
            'Nara' => 29,
            '奈良県' => 29,
            'Wakayama' => 30,
            '和歌山県' => 30,
            'Tottori' => 31,
            '鳥取県' => 31,
            'Shimane' => 32,
            '島根県' => 32,
            'Okayama' => 33,
            '岡山県' => 33,
            'Hiroshima' => 34,
            '広島県' => 34,
            'Yamaguchi' => 35,
            '山口県' => 35,
            'Tokushima' => 36,
            '徳島県' => 36,
            'Kagawa' => 37,
            '香川県' => 37,
            'Ehime' => 38,
            '愛媛県' => 38,
            'Kochi' => 39,
            '高知県' => 39,
            'Fukuoka' => 40,
            '福岡県' => 40,
            'Saga' => 41,
            '佐賀県' => 41,
            'Nagasaki' => 42,
            '長崎県' => 42,
            'Kumamoto' => 43,
            '熊本県' => 43,
            'Oita' => 44,
            '大分県' => 44,
            'Miyazaki' => 45,
            '宮崎県' => 45,
            'Kagoshima' => 46,
            '鹿児島県' => 46,
            'Okinawa' => 47,
            '沖縄県' => 47
        );
        return $arrPref[$prefName];
    }

}
