<?php
namespace Plugin\PayPalExpress;

use Eccube\Util\Str;
use Plugin\PayPalExpress\Entity\PayPalExpress;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class PayPalExpressEvent
{

    /** @var  \Eccube\Application $app */
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * カート画面に「PayPal でチェックアウト」ボタンを表示
     *
     * @param FilterResponseEvent $event
     */
    public function onRenderPayPalExpressCartBefore(FilterResponseEvent $event)
    {

        $request = $event->getRequest();
        $response = $event->getResponse();

        $PayPalExpress = $this->app['eccube.plugin.repository.paypal_express']->find(1);

        if ($PayPalExpress->getUseExpressBtn()) {
            $html = $this->getHtmlCart($request, $response, $PayPalExpress);
            $response->setContent($html);
        }

        $event->setResponse($response);
    }

    /**
     * ショッピングログイン画面に「PayPal でチェックアウト」ボタンを表示
     *
     * @param FilterResponseEvent $event
     */
    public function onRenderPayPalExpressShoppingLoginBefore(FilterResponseEvent $event)
    {

        $request = $event->getRequest();
        $response = $event->getResponse();

        $PayPalExpress = $this->app['eccube.plugin.repository.paypal_express']->find(1);

        if ($PayPalExpress->getUseExpressBtn()) {
            $html = $this->getHtmlShoppingLogin($request, $response, $PayPalExpress);
            $response->setContent($html);
        }

        $event->setResponse($response);
    }

    /**
     * ショッピングゲスト購入入力画面に「PayPal でチェックアウト」ボタンを表示
     *
     * @param FilterResponseEvent $event
     */
    public function onRenderPayPalExpressShoppingNonmemberBefore(FilterResponseEvent $event)
    {

        $request = $event->getRequest();
        $response = $event->getResponse();

        $PayPalExpress = $this->app['eccube.plugin.repository.paypal_express']->find(1);

        if ($PayPalExpress->getUseExpressBtn()) {
            $html = $this->getHtmlShoppingNonmember($request, $response, $PayPalExpress);
            $response->setContent($html);
        }

        $event->setResponse($response);
    }

    /**
     * 注文内容画面でお支払い方法に「PayPal エクスプレスチェックアウト」を選択した時のリンク先変更
     * 支払方法設定画面で設定したPayPalロゴを表示
     *
     * @param FilterResponseEvent $event
     */
    public function onRenderPayPalExpressShoppingBefore(FilterResponseEvent $event)
    {

        $Order = $this->app['eccube.plugin.service.paypal_express']->getOrder();

        $request = $event->getRequest();
        $response = $event->getResponse();

        $PayPalExpress = $this->app['eccube.plugin.repository.paypal_express']->find(1);

        $Payment = $this->app['eccube.repository.payment']->find($PayPalExpress->getPaymentId());
        if ($Payment) {

            // PayPalが存在し、有効であればロゴを表示
            $html = $this->getHtmlShoppingPayPalLogo($request, $response, $PayPalExpress);
            $response->setContent($html);

            if ($Order) {
                // PayPalが選択されたらフォームのactionを書き換え
                $Payment = $Order->getPayment();

                if ($Payment) {
                    if ($Payment->getId() == $PayPalExpress->getPaymentId()) {

                        $html = $this->getHtmlShopping($request, $response, $PayPalExpress);
                        $response->setContent($html);

                    }
                }
            }
        }

        $event->setResponse($response);
    }

    /**
     * 支払方法設定画面で「PayPal エクスプレスチェックアウト」が選択されたらショートコードを登録する項目を表示
     *
     * @param FilterResponseEvent $event
     */
    public function onRenderPayPalExpressAdminPaymentBefore(FilterResponseEvent $event)
    {

        if ($this->app->isGranted('ROLE_ADMIN')) {

            $request = $event->getRequest();
            $response = $event->getResponse();

            $PayPalExpress = $this->app['eccube.plugin.repository.paypal_express']->find(1);

            // payment_id
            $id = $request->get('id');
            $Payment = $this->app['eccube.repository.payment']->find($id);

            // パラメータのIDと登録されているpayment_idが一致していればPayPalエクスプレスの支払方法が選択されている
            if ($Payment && $PayPalExpress->getPaymentId() == $Payment->getId()) {

                list($html, $form) = $this->getHtmlPaymentShortCode($request, $response, $PayPalExpress);
                $response->setContent($html);

                if ('POST' === $request->getMethod()) {
                    // PaymentControllerの登録成功時のみ処理を通す
                    // RedirectResponseかどうかで判定する.
                    if (!$response instanceof RedirectResponse) {
                        return;
                    }

                    if ($form->isValid()) {

                        $PayPalExpress = $this->app['eccube.plugin.repository.paypal_express']->find(1);

                        $data = $form->get('payment_paypal_logo')->getData();

                        $PayPalExpress->setPaymentPaypalLogo($data);

                        $this->app['orm.em']->flush($PayPalExpress);
                    }
                }

            }

            $event->setResponse($response);
        }
    }

    /**
     * ボタンを追加したhtmlを作成
     *
     * @param Request $request
     * @param Response $response
     * @param PayPalExpress $PayPalExpress
     * @return string
     */
    private function getHtmlCart(Request $request, Response $response, PayPalExpress $PayPalExpress)
    {

        $crawler = new Crawler($response->getContent());

        $html = $this->getHtml($crawler);

        $parts = $this->app->renderView('PayPalExpress/Resource/template/cart_parts.twig');

        if ($PayPalExpress->getInContext() == '1') {

            $deliveries = $this->getDeliveries($PayPalExpress);

            // 配送業社が1件しかない場合、In-Contextを使用する
            if (count($deliveries) == 1) {
                $inContextParts = $this->app->renderView('PayPalExpress/Resource/template/in_context.twig', array(
                    'PayPalExpress' => $PayPalExpress,
                ));
                $parts .= $inContextParts;
            }
        }

        try {
            $oldHtml = $crawler->filter('.btn_group')->last()->html();
            $newHtml = $oldHtml . $parts;
            $html = str_replace($oldHtml, $newHtml, $html);

        } catch (\InvalidArgumentException $e) {
        }

        return html_entity_decode($html, ENT_NOQUOTES, 'UTF-8');

    }

    /**
     * PayPalロゴを追加したhtmlを作成
     *
     * @param Request $request
     * @param Response $response
     * @param PayPalExpress $PayPalExpress
     * @return string
     */
    private function getHtmlShoppingLogin(Request $request, Response $response, PayPalExpress $PayPalExpress)
    {
        $crawler = new Crawler($response->getContent());

        $html = $this->getHtml($crawler);

        $parts = $this->app->renderView('PayPalExpress/Resource/template/paypal_parts.twig');

        if ($PayPalExpress->getInContext() == '1') {
            // In-Contextを使用する
            $deliveries = $this->getDeliveries($PayPalExpress);

            // 配送業社が1件しかない場合、In-Contextを使用する
            if (count($deliveries) == 1) {
                $inContextParts = $this->app->renderView('PayPalExpress/Resource/template/in_context.twig', array(
                    'PayPalExpress' => $PayPalExpress,
                ));
                $parts .= $inContextParts;
            }
        }

        try {
            $oldHtml = $crawler->filter('#main_middle')->first()->html();

            $newHtml = $parts . $oldHtml;
            $html = str_replace($oldHtml, $newHtml, $html);

        } catch (\InvalidArgumentException $e) {
        }
        return html_entity_decode($html, ENT_NOQUOTES, 'UTF-8');
    }

    /**
     * PayPalロゴを追加したhtmlを作成
     *
     * @param Request $request
     * @param Response $response
     * @param PayPalExpress $PayPalExpress
     * @return string
     */
    private function getHtmlShoppingNonmember(Request $request, Response $response, PayPalExpress $PayPalExpress)
    {
        $crawler = new Crawler($response->getContent());
        $html = $this->getHtml($crawler);

        $parts = $this->app->renderView('PayPalExpress/Resource/template/paypal_parts.twig');

        if ($PayPalExpress->getInContext() == '1') {
            // In-Contextを使用する
            $deliveries = $this->getDeliveries($PayPalExpress);

            // 配送業社が1件しかない場合、In-Contextを使用する
            if (count($deliveries) == 1) {
                $inContextParts = $this->app->renderView('PayPalExpress/Resource/template/in_context.twig', array(
                    'PayPalExpress' => $PayPalExpress,
                ));
                $parts .= $inContextParts;
            }
        }

        try {
            $oldHtml = $crawler->filter('#main_middle')->first()->html();

            $newHtml = $parts . $oldHtml;
            $html = str_replace($oldHtml, $newHtml, $html);
        } catch (\InvalidArgumentException $e) {
        }
        return html_entity_decode($html, ENT_NOQUOTES, 'UTF-8');

    }

    /**
     * PayPalの支払方法にロゴを追加するhtmlを作成
     *
     * @param Request $request
     * @param Response $response
     * @param PayPalExpress $PayPalExpress
     * @return string
     */
    private function getHtmlShoppingPayPalLogo(Request $request, Response $response, PayPalExpress $PayPalExpress)
    {
        $crawler = new Crawler($response->getContent());
        $html = $this->getHtml($crawler);

        // 設定画面で選択されたロゴを表示
        $paypalLogo = '';
        if (!is_null($PayPalExpress->getPaymentPaypalLogo())) {
            $paypalLogo = $this->app['config']['paypal_express_payment_paypal_logo_' . $PayPalExpress->getPaymentPaypalLogo()];
        }

        $parts = $this->app->renderView('PayPalExpress/Resource/template/payment_logo.twig', array(
            'paypalLogo' => $paypalLogo,
        ));
        try {
            $oldHtml = $crawler->filter('#shopping_payment_' . $PayPalExpress->getPaymentId())->parents()->parents()->parents()->html();

            $newHtml = $oldHtml . $parts;
            $html = str_replace($oldHtml, $newHtml, $html);
        } catch (\InvalidArgumentException $e) {
        }
        return html_entity_decode($html, ENT_QUOTES, 'UTF-8');
    }

    /**
     * フォームを書き換えるhtmlを作成
     *
     * @param Request $request
     * @param Response $response
     * @param PayPalExpress $PayPalExpress
     * @return string
     */
    private function getHtmlShopping(Request $request, Response $response, PayPalExpress $PayPalExpress)
    {
        $crawler = new Crawler($response->getContent());
        $html = $this->getHtml($crawler);

        $parts = $this->app->renderView('PayPalExpress/Resource/template/payment_form.twig');

        $inContextParts = $this->app->renderView('PayPalExpress/Resource/template/in_context.twig', array(
            'PayPalExpress' => $PayPalExpress,
        ));

        $inContextButtonParts = $this->app->renderView('PayPalExpress/Resource/template/in_context_button.twig');

        try {

            if ($PayPalExpress->getInContext() == '1') {
                // In-Contextを使用する
                $oldHtml = $crawler->filter('form')->last()->parents()->html();
                $newHtml = $oldHtml . $inContextParts;
                $html = str_replace($oldHtml, $newHtml, $html);
            }

            $oldHtml = $crawler->filter('#shopping-form')->parents()->first()->html();

            $str = Str::convertLineFeed($oldHtml);

            $contents = explode("\n", $str);

            foreach ($contents as &$content) {
                if (strpos($content, 'shopping-form') !== false) {
                    $content = $parts;
                    break;
                }
            }

            foreach ($contents as &$content) {
                if (strpos($content, 'btn btn-primary btn-block') !== false) {
                    $content = $inContextButtonParts;
                    break;
                }
            }

            $newHtml = implode("\n", $contents);

            $html = str_replace($oldHtml, $newHtml, $html);

        } catch (\InvalidArgumentException $e) {
        }
        return html_entity_decode($html, ENT_NOQUOTES, 'UTF-8');
    }

    /**
     * 支払方法設定画面にフォーム項目を追加するhtmlを作成
     *
     * @param Request $request
     * @param Response $response
     * @param PayPalExpress $PayPalExpress
     * @return string
     */
    private function getHtmlPaymentShortCode(Request $request, Response $response, PayPalExpress $PayPalExpress)
    {
        $crawler = new Crawler($response->getContent());
        $html = $this->getHtml($crawler);

        // ショートコードを表示する
        $form = $this->app['form.factory']->createBuilder('payment_register')->getForm();

        $form->get('payment_paypal_logo')->setData($PayPalExpress->getPaymentPaypalLogo());

        $form->handleRequest($request);

        $parts = $this->app->renderView('PayPalExpress/Resource/template/admin/payment_paypal_logo.twig', array(
            'form' => $form->createView()
        ));

        try {
            $oldHtml = $crawler->filter('.form-group')->last()->parents()->html();

            $newHtml = $oldHtml . $parts;
            $html = str_replace($oldHtml, $newHtml, $html);

        } catch (\InvalidArgumentException $e) {
        }

        return array(html_entity_decode($html, ENT_QUOTES, 'UTF-8'), $form);
    }

    /**
     * 解析用HTMLを取得
     *
     * @param Crawler $crawler
     * @return string
     */
    private function getHtml(Crawler $crawler)
    {
        $html = '';
        foreach ($crawler as $domElement) {
            $domElement->ownerDocument->formatOutput = true;
            $html .= $domElement->ownerDocument->saveHTML();
        }
        return html_entity_decode($html, ENT_NOQUOTES, 'UTF-8');
    }

    /**
     * 配送業者情報を取得
     *
     * @param PayPalExpress $PayPalExpress
     * @return mixed
     */
    private function getDeliveries(PayPalExpress $PayPalExpress)
    {
        $cartItems = $this->app['eccube.service.cart']->getCart()->getCartItems();

        // 受注詳細, 配送商品
        $productTypes = array();
        foreach ($cartItems as $item) {
            $ProductClass = $item->getObject();
            $productTypes[] = $ProductClass->getProductType();
        }

        // PayPalで配送可能な配送業者を取得
        $qb = $this->app['orm.em']->createQueryBuilder();
        $deliveries = $qb->select('d')
            ->from('\Eccube\Entity\Delivery', 'd')
            ->innerJoin('Eccube\Entity\PaymentOption', 'po', 'WITH', 'po.delivery_id = d.id')
            ->innerJoin('Eccube\Entity\Payment', 'p', 'WITH', 'p.id = po.payment_id')
            ->where('d.ProductType in (:productTypes)')
            ->andWhere('p.id = :paymentId')
            ->setParameter('productTypes', $productTypes)
            ->setParameter('paymentId', $PayPalExpress->getPaymentId())
            ->orderBy("d.rank", "ASC")
            ->getQuery()
            ->getResult();

        return $deliveries;

    }

}
