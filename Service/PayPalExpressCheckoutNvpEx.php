<?php

namespace Plugin\PayPalExpress\Service;

use Eccube\Application;
use Guzzle\Http\Client;
use Guzzle\Http\Message\Response;
use Guzzle\Log\MessageFormatter;
use Guzzle\Log\PsrLogAdapter;
use Guzzle\Plugin\Log\LogPlugin;
use Plugin\PayPalExpress\Entity\PayPalExpress;
use Plugin\PayPalExpress\Exception\PayPalExpressPdrException;
use Symfony\Component\HttpFoundation\Session\Session;

class PayPalExpressCheckoutNvpEx
{

    /**
     * PayPal通信 : SetExpressCheckout
     */
    const PAYPAL_METHOD_SET_EXPRESS_CHECKOUT = 'SetExpressCheckout';
    /**
     * PayPal通信 : GetExpressCheckoutDetails
     */
    const PAYPAL_METHOD_GET_EXPRESS_CHECKOUT_DETAILS = 'GetExpressCheckoutDetails';
    /**
     * PayPal通信 : DoExpressCheckoutPayment
     */
    const PAYPAL_METHOD_DO_EXPRESS_CHECKOUT_PAYMENT = 'DoExpressCheckoutPayment';
    /**
     * PayPal通信結果 : Success
     */
    const PAYPAL_PAYMENT_ACTION_SUCCESS = 'Success';
    /**
     * PayPal通信結果 : SuccessWithWarning
     */
    const PAYPAL_PAYMENT_ACTION_SUCCESS_WITH_WARNING = 'SuccessWithWarning';

    /** @var \Eccube\Application */
    protected $app;

    /** @var  \Plugin\PayPalExpress\Entity\PayPalExpress */
    protected $payPalExpress;

    /**
     * @var array PayPalパラメータ
     */
    protected $options;

    /**
     * @var \Monolog\Logger
     */
    protected $logger;

    /**
     * @var string PayPalのエンドポイント
     */
    protected $endPoint;

    /**
     * @var string PayPal merchant用画面
     */
    protected $payPalUrl;

    /**
     * @var Session
     */
    protected $session;


    public function __construct(Application $app, PayPalExpress $PayPalExpress)
    {
        $this->app = $app;
        $this->payPalExpress = $PayPalExpress;
        $sandbox = $PayPalExpress->getUseSandbox();
        $this->endPoint = ($sandbox) ? $this->app['config']['paypal_express_endpoint_sandbox'] : $this->app['config']['paypal_express_endpoint'];
        $this->payPalUrl = ($sandbox) ? $this->app['config']['paypal_express_paypal_url_sandbox'] : $this->app['config']['paypal_express_paypal_url'];

        $this->options = array(
            'USER' => $PayPalExpress->getApiUser(),
            'PWD' => $PayPalExpress->getApiPassword(),
            'SIGNATURE' => $PayPalExpress->getApiSignature(),
            'PAYMENTREQUEST_0_CURRENCYCODE' => $app['config']['paypal_express_currency_code'],
            'PAYMENTREQUEST_0_PAYMENTACTION' => $app['config']['paypal_express_paymentaction'],
            'VERSION' => $app['config']['paypal_express_version'],
        );

        $this->logger = $app['monolog.paypal'];

        $this->session = $app['session'];

    }

    /**
     * PayPal通信処理
     *
     * @param array $options
     * @return Response
     */
    protected function sendPayPalExpressCheckoutNvp(array $options)
    {

        $logAdapter = new PsrLogAdapter($this->logger);
        $logPlugin = new LogPlugin($logAdapter, MessageFormatter::DEBUG_FORMAT);

        $client = new Client();

        $client->addSubscriber($logPlugin);

        $request = $client->post($this->endPoint, null, $options);

        return $request->send();
    }

    /**
     * PayPalからの戻り値を取得
     *
     * @param Response $response
     * @return array
     * @throws PayPalExpressPdrException
     * @throws \Exception
     */
    protected function getPayPalExpressCheckoutNvp(Response $response)
    {
        $responseArray = array();

        parse_str($response->getBody(true), $responseArray);

        if (!empty($responseArray['ACK']) &&
            !in_array($responseArray['ACK'], array(self::PAYPAL_PAYMENT_ACTION_SUCCESS, self::PAYPAL_PAYMENT_ACTION_SUCCESS_WITH_WARNING))
        ) {
            $this->logger->addError($responseArray['ACK'], $responseArray);
            if ($responseArray['L_ERRORCODE0'] == '10486') {
                // PDRエラー用Exception
                throw new PayPalExpressPdrException();
            }
            throw new \Exception(sprintf('ErrorCode: %s, Error: %s', $responseArray['L_ERRORCODE0'], $responseArray['L_LONGMESSAGE0']));
        }

        if (empty($responseArray['TOKEN'])) {
            // Tokenが存在しなければエラー
            throw new \Exception('Invalid token');
        }

        return $responseArray;
    }

}
