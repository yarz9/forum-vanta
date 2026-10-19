<?php

namespace Vanta\Loader;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function install(array $stepParams = [])
    {
        $this->schemaCreate();
    }

    public function upgrade(array $stepParams = [], &$previousVersion = false)
    {
        $this->schemaCreate();

        $sm = $this->app->db()->getSchemaManager();

        if ($sm->tableExists('xf_vanta_device') && !$sm->columnExists('xf_vanta_device', 'hwid')) {
            $sm->alterTable('xf_vanta_device', function (\XF\Db\Schema\Alter $table) {
                $table->addColumn('hwid', 'varchar', 64)->nullable();
            });
        }

        if ($sm->tableExists('xf_vanta_device') && !$sm->columnExists('xf_vanta_device', 'specs')) {
            $sm->alterTable('xf_vanta_device', function (\XF\Db\Schema\Alter $table) {
                $table->addColumn('specs', 'text')->nullable();
            });
        }

        if ($sm->tableExists('xf_vanta_log') && !$sm->columnExists('xf_vanta_log', 'hwid')) {
            $sm->alterTable('xf_vanta_log', function (\XF\Db\Schema\Alter $table) {
                $table->addColumn('hwid', 'varchar', 64)->nullable();
            });
        }
    }

    public function uninstall(array $stepParams = [])
    {
        $this->schemaDrop();
    }

    protected function schemaCreate()
    {
        $sm = $this->app->db()->getSchemaManager();

        if (!$sm->tableExists('xf_vanta_session')) {
            $sm->createTable('xf_vanta_session', function (\XF\Db\Schema\Create $table) {
                $table->addColumn('session_token', 'varbinary', 32);
                $table->addColumn('user_id', 'int');
                $table->addColumn('token_hash', 'varbinary', 64);
                $table->addColumn('created', 'int');
                $table->addColumn('expires', 'int');
                $table->addColumn('device_token', 'varbinary', 64)->nullable();
                $table->addColumn('login_ip', 'varbinary', 16)->nullable();
                $table->addColumn('login_user_agent', 'varchar', 255)->nullable();
                $table->addColumn('last_activity', 'int');
                $table->addPrimaryKey('session_token');
                $table->addKey('token_hash');
            });
        }

        if (!$sm->tableExists('xf_vanta_device')) {
            $sm->createTable('xf_vanta_device', function (\XF\Db\Schema\Create $table) {
                $table->addColumn('device_id', 'int')->autoIncrement();
                $table->addColumn('user_id', 'int');
                $table->addColumn('hwid_hash', 'varbinary', 64);
                $table->addColumn('hwid', 'varchar', 64)->nullable();
                $table->addColumn('specs', 'text')->nullable();
                $table->addColumn('device_name', 'varchar', 255);
                $table->addColumn('created', 'int');
                $table->addColumn('last_seen', 'int');
                $table->addColumn('create_ip', 'varbinary', 16)->nullable();
                $table->addPrimaryKey('device_id');
                $table->addKey('user_id');
                $table->addKey('hwid_hash');
                $table->addUniqueKey(['user_id', 'hwid_hash']);
            });
        }

        if (!$sm->tableExists('xf_vanta_ban')) {
            $sm->createTable('xf_vanta_ban', function (\XF\Db\Schema\Create $table) {
                $table->addColumn('ban_id', 'int')->autoIncrement();
                $table->addColumn('user_id', 'int')->nullable();
                $table->addColumn('hwid_hash', 'varbinary', 64)->nullable();
                $table->addColumn('reason', 'varchar', 255);
                $table->addColumn('created_by', 'int');
                $table->addColumn('created_by_username', 'varchar', 50);
                $table->addColumn('created', 'int');
                $table->addColumn('expires', 'int')->nullable();
                $table->addColumn('type', 'varchar', 20);
                $table->addPrimaryKey('ban_id');
                $table->addKey('user_id');
                $table->addKey('hwid_hash');
            });
        }

        if (!$sm->tableExists('xf_vanta_maint')) {
            $sm->createTable('xf_vanta_maint', function (\XF\Db\Schema\Create $table) {
                $table->addColumn('setting_key', 'varchar', 50);
                $table->addColumn('setting_value', 'varchar', 255);
                $table->addPrimaryKey('setting_key');
            });
        }

        $this->app->db()->query("
            INSERT IGNORE INTO xf_vanta_maint (setting_key, setting_value) VALUES
            ('enabled', '0'),
            ('message', ''),
            ('min_version', ''),
            ('download_url', ''),
            ('support_url', '')
        ");

        if (!$sm->tableExists('xf_vanta_log')) {
            $sm->createTable('xf_vanta_log', function (\XF\Db\Schema\Create $table) {
                $table->addColumn('log_id', 'int')->autoIncrement();
                $table->addColumn('user_id', 'int')->nullable();
                $table->addColumn('hwid_hash', 'varbinary', 64)->nullable();
                $table->addColumn('hwid', 'varchar', 64)->nullable();
                $table->addColumn('ip_address', 'varbinary', 16)->nullable();
                $table->addColumn('event_type', 'varchar', 50);
                $table->addColumn('detail', 'varchar', 255)->nullable();
                $table->addColumn('timestamp', 'int');
                $table->addPrimaryKey('log_id');
                $table->addKey('user_id');
                $table->addKey('event_type');
            });
        }

        if (!$sm->tableExists('xf_vanta_reset')) {
            $sm->createTable('xf_vanta_reset', function (\XF\Db\Schema\Create $table) {
                $table->addColumn('reset_id', 'int')->autoIncrement();
                $table->addColumn('user_id', 'int');
                $table->addColumn('hwid_hash', 'varbinary', 64);
                $table->addColumn('reason', 'varchar', 255);
                $table->addColumn('requested_by', 'int');
                $table->addColumn('requested_by_username', 'varchar', 50);
                $table->addColumn('approved_by', 'int')->nullable();
                $table->addColumn('approved_by_username', 'varchar', 50)->nullable();
                $table->addColumn('created', 'int');
                $table->addColumn('processed', 'int')->nullable();
                $table->addColumn('status', 'varchar', 20)->setDefault('pending');
                $table->addPrimaryKey('reset_id');
                $table->addKey('user_id');
                $table->addKey('status');
            });
        }
    }

    protected function schemaDrop()
    {
        $sm = $this->app->db()->getSchemaManager();
        $sm->dropTable('xf_vanta_session');
        $sm->dropTable('xf_vanta_device');
        $sm->dropTable('xf_vanta_ban');
        $sm->dropTable('xf_vanta_maint');
        $sm->dropTable('xf_vanta_log');
        $sm->dropTable('xf_vanta_reset');
    }
}