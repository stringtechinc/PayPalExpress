<?php

namespace Plugin\PayPalExpress;

use Eccube\Common\Constant;
use Eccube\Entity\BlockPosition;
use Eccube\Entity\Master\DeviceType;
use Eccube\Entity\PageLayout;
use Eccube\Entity\Payment;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Util\Cache;
use Plugin\PayPalExpress\Entity\PayPalExpress;
use Symfony\Component\Filesystem\Filesystem;

class PluginManager extends AbstractPluginManager
{

    /**
     * @var string コピー元リソースディレクトリ
     */
    private $origin;

    /**
     * @var string コピー先リソースディレクトリ
     */
    private $target;

    /**
     * @var string コピー元ブロックファイル
     */
    private $originBlock;

    /**
     * @var string ブロックファイル名
     */
    private $blockFileName = 'paypalexpress_paypal_block';

    /**
     * @var string 支払方法
     */
    private $paymentMethod = 'PayPal エクスプレスチェックアウト';

    /**
     * @var string PayPalBlock名
     */
    private $paypalLogo = 'PayPalロゴ';


    public function __construct()
    {
        // コピー元のディレクトリ
        $this->origin = __DIR__ . '/Resource/assets';
        // コピー先のディレクトリ
        $this->target = __DIR__ . '/../../../html/plugin/paypalexpress';
        // コピー元ブロックファイル
        $this->originBlock = __DIR__ . '/Resource/template/Block/' . $this->blockFileName . '.twig';
    }

    /**
     * プラグインインストール時の処理
     *
     * @param $config
     * @param $app
     * @throws \Exception
     */
    public function install($config, $app)
    {
        $this->migrationSchema($app, __DIR__ . '/Resource/doctrine/migration', $config['code']);

        // リソースファイルのコピー
        $this->copyAssets();

    }

    /**
     * プラグイン削除時の処理
     *
     * @param $config
     * @param $app
     */
    public function uninstall($config, $app)
    {

        $PayPalExpress = $app['orm.em']->getRepository('Plugin\PayPalExpress\Entity\PayPalExpress')->find(1);
        if ($PayPalExpress) {
            // dtb_paymentに削除フラグ更新
            $Payment = $app['eccube.repository.payment']->find($PayPalExpress->getPaymentId());
            if ($Payment) {
                $Payment->setDelFlg(Constant::ENABLED);
                $app['orm.em']->flush($Payment);
            }
        }

        // ブロックの削除
        $this->removeBlock($app);

        // リソースファイルの削除
        $this->removeAssets();

        $this->migrationSchema($app, __DIR__ . '/Resource/doctrine/migration', $config['code'], 0);
    }

    /**
     * プラグイン有効時の処理
     *
     * @param $config
     * @param $app
     * @throws \Exception
     */
    public function enable($config, $app)
    {

        $em = $app['orm.em'];
        $em->getConnection()->beginTransaction();
        try {
            // soft_deleteを無効にする
            $softDeleteFilter = $em->getFilters()->getFilter('soft_delete');
            $softDeleteFilter->setExcludes(array(
                'Eccube\Entity\Payment'
            ));

            // serviceで定義している情報が取得できないため、直接呼び出す
            try {
                // EC-CUBE3.0.3対応
                $PayPalExpress = $em->getRepository('Plugin\PayPalExpress\Entity\PayPalExpress')->find(1);
            } catch (\Exception $e) {
                return null;
            }

            if (!$PayPalExpress) {
                // 存在しなければdtb_paymentもまだ作成されていない

                $Payment = new Payment();

                $rank = $app['eccube.repository.payment']->findOneBy(array(), array('rank' => 'DESC'))
                        ->getRank() + 1;

                $Payment->setMethod($this->paymentMethod);
                $Payment->setCharge(0);
                $Payment->setRuleMin(0);
                $Payment->setFixFlg(Constant::ENABLED);
                $Payment->setChargeFlg(Constant::ENABLED);
                $Payment->setRank($rank);
                $Payment->setDelFlg(Constant::DISABLED);

                $em->persist($Payment);
                $em->flush($Payment);

                // payment_idを設定
                $PayPalExpress = new PayPalExpress();

                // IDは1固定
                $PayPalExpress->setId(1);
                $PayPalExpress->setPaymentId($Payment->getId());
                // 「PayPalで購入手続きに進む」ボタンを使用する
                $PayPalExpress->setUseExpressBtn(true);
                // Block内で表示するロゴ
                $PayPalExpress->setPaypalLogo(2);
                // 支払方法で表示するロゴ
                $PayPalExpress->setPaymentPaypalLogo(1);
                // In-Contextを使用(初期値は使用しない)
                $PayPalExpress->setInContext('2');
                $em->persist($PayPalExpress);
                $em->flush($PayPalExpress);

            } else {

                // dtb_paymentに更新
                $Payment = $app['eccube.repository.payment']->find($PayPalExpress->getPaymentId());

                // 「PayPalで購入手続きに進む」ボタンを使用する
                $PayPalExpress->setUseExpressBtn(true);
                $em->flush($PayPalExpress);


                if ($Payment) {
                    $Payment->setDelFlg(Constant::DISABLED);
                    $em->flush($Payment);
                }
            }

            // ブロックへ登録
            $this->copyBlock($app);

            $em->getConnection()->commit();
        } catch (\Exception $e) {
            $em->getConnection()->rollback();
            throw $e;
        }

    }

    /**
     * プラグイン無効時の処理
     *
     * @param $config
     * @param $app
     */
    public function disable($config, $app)
    {
        $PayPalExpress = $app['orm.em']->getRepository('Plugin\PayPalExpress\Entity\PayPalExpress')->find(1);
        if ($PayPalExpress) {
            $Payment = $app['eccube.repository.payment']->find($PayPalExpress->getPaymentId());
            if ($Payment) {
                $Payment->setDelFlg(Constant::ENABLED);
                $app['orm.em']->flush($Payment);
            }
        }

        // ブロックの削除
        $this->removeBlock($app);
    }

    public function update($config, $app)
    {
        // 最新のテーブルへmigration
        $this->migrationSchema($app, __DIR__ . '/Resource/doctrine/migration', $config['code']);
    }


    /**
     * 画像ファイル等をコピー
     */
    private function copyAssets()
    {
        $file = new Filesystem();
        $file->mirror($this->origin, $this->target . '/assets');
    }

    /**
     * コピーした画像ファイルなどを削除
     */
    private function removeAssets()
    {
        $file = new Filesystem();
        $file->remove($this->target);
    }

    /**
     * PayPal用ブロックを登録
     *
     * @param $app
     * @throws \Exception
     */
    private function copyBlock($app)
    {

        // ファイルコピー
        $file = new Filesystem();
        // ブロックファイルをコピー
        $file->copy($this->originBlock, $app['config']['block_realdir'] . '/' . $this->blockFileName . '.twig');

        $em = $app['orm.em'];
        $em->getConnection()->beginTransaction();
        try {
            $DeviceType = $app['eccube.repository.master.device_type']->find(DeviceType::DEVICE_TYPE_PC);

            /** @var \Eccube\Entity\Block $Block */
            $Block = $app['eccube.repository.block']->findOrCreate(null, $DeviceType);

            // Blockの登録
            $Block->setName($this->paypalLogo);
            $Block->setFileName($this->blockFileName);
            $Block->setDeletableFlg(Constant::ENABLED);
            $em->persist($Block);
            $em->flush($Block);

            // BlockPositionの登録
            $blockPos = $em->getRepository('Eccube\Entity\BlockPosition')->findOneBy(
                array('page_id' => 1, 'target_id' => PageLayout::TARGET_ID_FOOTER),
                array('block_row' => 'ASC'));

            // ブロックの順序を変更
            if ($blockPos) {
                $blockRow = $blockPos->getBlockRow() + 1;
                $blockPos->setBlockRow($blockRow);
                $em->flush($blockPos);
            }

            $PageLayout = $app['eccube.repository.page_layout']->find(1);

            $BlockPosition = new BlockPosition();
            $BlockPosition->setPageLayout($PageLayout);
            $BlockPosition->setPageId($PageLayout->getId());
            $BlockPosition->setTargetId(PageLayout::TARGET_ID_FOOTER);
            $BlockPosition->setBlock($Block);
            $BlockPosition->setBlockId($Block->getId());
            // footerの1番目にセット
            $BlockPosition->setBlockRow(1);
            $BlockPosition->setAnywhere(Constant::ENABLED);

            $em->persist($BlockPosition);
            $em->flush($BlockPosition);

            $em->getConnection()->commit();

        } catch (\Exception $e) {
            $em->getConnection()->rollback();
            throw $e;
        }

    }

    /**
     * PayPal用ブロックを削除
     *
     * @param $app
     * @throws \Exception
     */
    private function removeBlock($app)
    {
        $file = new Filesystem();
        $file->remove($app['config']['block_realdir'] . '/' . $this->blockFileName . '.twig');

        // Blockの取得(file_nameはアプリケーションの仕組み上必ずユニーク)
        /** @var \Eccube\Entity\Block $Block */
        $Block = $app['eccube.repository.block']->findOneBy(array('file_name' => $this->blockFileName));

        if ($Block) {
            $em = $app['orm.em'];
            $em->getConnection()->beginTransaction();

            try {
                // BlockPositionの削除
                $blockPositions = $Block->getBlockPositions();
                /** @var \Eccube\Entity\BlockPosition $BlockPosition */
                foreach ($blockPositions as $BlockPosition) {
                    $Block->removeBlockPosition($BlockPosition);
                    $em->remove($BlockPosition);
                }

                // Blockの削除
                $em->remove($Block);

                $em->flush();
                $em->getConnection()->commit();

            } catch (\Exception $e) {
                $em->getConnection()->rollback();
                throw $e;
            }
        }

        Cache::clear($app, false);

    }
}
