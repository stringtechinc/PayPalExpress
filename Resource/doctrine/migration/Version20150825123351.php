<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20150825123351 extends AbstractMigration
{

    const NAME = 'plg_paypal_express';

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        if ($schema->hasTable(self::NAME)) {
            return true;
        }
        $table = $schema->createTable(self::NAME);
        $table->addColumn('paypal_id', 'integer', array(
            'unsigned' => true
        ));
        $table->addColumn('api_user', 'text', array('notnull' => false));
        $table->addColumn('api_password', 'text', array('notnull' => false));
        $table->addColumn('api_signature', 'text', array('notnull' => false));
        $table->addColumn('use_express_btn', 'boolean', array('notnull' => false));
        $table->addColumn('corporate_logo', 'text', array('notnull' => false));
        $table->addColumn('border_color', 'text', array('notnull' => false));
        $table->addColumn('use_sandbox', 'boolean', array('notnull' => false));
        $table->addColumn('paypal_logo', 'text', array('notnull' => false));
        $table->addColumn('payment_paypal_logo', 'text', array('notnull' => false));
        $table->addColumn('is_configured', 'smallint', array('notnull' => false));
        $table->addColumn('payment_id', 'integer', array(
            'unsigned' => true
        ));
        $table->addColumn('in_context', 'text', array('notnull' => false));

        $table->setPrimaryKey(array('paypal_id'));
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        if (!$schema->hasTable(self::NAME)) {
            return true;
        }
        $schema->dropTable(self::NAME);
    }
}
