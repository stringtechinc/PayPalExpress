<?php

namespace Plugin\PayPalExpress\ServiceProvider;

use Eccube\Application;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\WebProcessor;
use Plugin\PayPalExpress\Form\Extension\PaymentRegisterTypeExtension;
use Plugin\PayPalExpress\Form\Type\PayPalExpressConfigType;
use Plugin\PayPalExpress\Form\Type\PayPalExpressDeliveryType;
use Silex\Application as BaseApplication;
use Silex\ServiceProviderInterface;
use Symfony\Component\Yaml\Yaml;

class PayPalExpressServiceProvider implements ServiceProviderInterface
{
    public function register(BaseApplication $app)
    {
        // 管理画面
        $app->match('/' . $app['config']['admin_route'] . '/plugin/paypalexpress/config', 'Plugin\PayPalExpress\Controller\ConfigController::index')->bind('plugin_PayPalExpress_config');
        $app->match('/' . $app['config']['admin_route'] . '/plugin/paypalexpress/config_complete', 'Plugin\PayPalExpress\Controller\ConfigController::complete')->bind('plugin_PayPalExpress_config_complete');

        // PayPalExpress Checkout Payment
        $app->match('/plugin/paypalexpress/checkout', 'Plugin\PayPalExpress\Controller\PayPalExpressController::checkout')->bind('plugin_paypal_express_payment_checkout')->value('param', 'payment');
        // PayPalExpress Checkout Shortcut
        $app->match('/plugin/paypalexpress/shortcut_checkout', 'Plugin\PayPalExpress\Controller\PayPalExpressController::shortcutCheckout')->bind('plugin_paypal_express_shortcut_checkout')->value('param', 'shortcut');
        // PayPalExpress Checkout Shortcut Delivery
        $app->match('/plugin/paypalexpress/delivery', 'Plugin\PayPalExpress\Controller\PayPalExpressController::delivery')->bind('plugin_paypal_express_delivery')->value('param', 'shortcut');
        // PayPalExpress Checkout Confirm
        $app->match('/plugin/paypalexpress/confirm', 'Plugin\PayPalExpress\Controller\PayPalExpressController::confirm')->bind('plugin_paypal_express_confirm');
        // PayPalExpress Checkout Complete
        $app->match('/plugin/paypalexpress/complete', 'Plugin\PayPalExpress\Controller\PayPalExpressController::complete')->bind('plugin_paypal_express_complete');
        // PayPalExpress Checkout Cancel
        $app->match('/plugin/paypalexpress/cancel', 'Plugin\PayPalExpress\Controller\PayPalExpressController::cancel')->bind('plugin_paypal_express_cancel');

        // Form
        $app['form.types'] = $app->share($app->extend('form.types', function ($types) use ($app) {
            $types[] = new PayPalExpressConfigType($app);
            $types[] = new PayPalExpressDeliveryType();
            return $types;
        }));

        // Form Extension
        $app['form.type.extensions'] = $app->share($app->extend('form.type.extensions', function ($extensions) use ($app) {
            $extensions[] = new PaymentRegisterTypeExtension($app);
            return $extensions;
        }));

        // Repository
        $app['eccube.plugin.repository.paypal_express'] = $app->share(function () use ($app) {
            return $app['orm.em']->getRepository('Plugin\PayPalExpress\Entity\PayPalExpress');
        });

        // Service
        $app['eccube.plugin.service.paypal_express'] = $app->share(function () use ($app) {
            return new \Plugin\PayPalExpress\Service\PayPalExpressService($app);
        });

        // メッセージ登録
        $app['translator'] = $app->share($app->extend('translator', function ($translator, \Silex\Application $app) {
            $translator->addLoader('yaml', new \Symfony\Component\Translation\Loader\YamlFileLoader());
            $file = __DIR__ . '/../Resource/locale/message.' . $app['locale'] . '.yml';
            if (file_exists($file)) {
                $translator->addResource('yaml', $file, $app['locale']);
            }
            return $translator;
        }));

        // load config
        $conf = $app['config'];
        $app['config'] = $app->share(function () use ($conf) {
            $confarray = array();
            $path_file = __DIR__.'/../Resource/config/path.yml';
            if (file_exists($path_file)) {
                $config_yml = Yaml::parse(file_get_contents($path_file));
                if (isset($config_yml)) {
                    $confarray = array_replace_recursive($confarray, $config_yml);
                }
            }

            $constant_file = __DIR__.'/../Resource/config/constant.yml';
            if (file_exists($constant_file)) {
                $config_yml = Yaml::parse(file_get_contents($constant_file));
                if (isset($config_yml)) {
                    $confarray = array_replace_recursive($confarray, $config_yml);
                }
            }

            return array_replace_recursive($conf, $confarray);
        });

        // paypal用ログファイル設定
        $app['monolog.paypal'] = $app->share(function ($app) {

            $logger = new $app['monolog.logger.class']('paypal.client');

            $file = $app['config']['root_dir'] . '/app/log/paypal.log';
            $RotateHandler = new RotatingFileHandler($file, $app['config']['log']['max_files'], Logger::INFO);
            $RotateHandler->setFilenameFormat(
                'paypal_{date}',
                'Y-m-d'
            );

            $token = substr($app['session']->getId(), 0, 8);
            $format = "[%datetime%] [".$token."] %channel%.%level_name%: %message% %context% %extra%\n";
            $RotateHandler->setFormatter(new LineFormatter($format));

            $logger->pushHandler(
                new FingersCrossedHandler(
                    $RotateHandler,
                    new ErrorLevelActivationStrategy(Logger::INFO)
                )
            );

            $logger->pushProcessor(function ($record) {
                // 出力ログからファイル名を削除し、lineを最終項目にセットしなおす
                unset($record['extra']['file']);
                $line = $record['extra']['line'];
                unset($record['extra']['line']);
                $record['extra']['line'] = $line;

                return $record;
            });

            $ip = new IntrospectionProcessor();
            $logger->pushProcessor($ip);

            $web = new WebProcessor();
            $logger->pushProcessor($web);

            $process = new ProcessIdProcessor();
            $logger->pushProcessor($process);

            return $logger;
        });

    }

    public function boot(BaseApplication $app)
    {
    }
}
