<?php

namespace Vanta\Loader;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstall;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstall;

    public function install(array $stepParams = [])
    {
        $this->schemaCreate();
    }

    public function upgrade(array $stepParams = [], &$previousVersion = false)
    {
        $this->schemaCreate();
    }

    public function uninstall(array $stepParams = [])
    {
        $this->schemaDrop();
    }

    protected function schemaCreate()
    {
        $schema = $this->app->db()->schema();

        if (!$schema->tableExists('xf_vanta_session')) {
            $schema->createTable('xf_vanta_session', function (\XF\Db\Schema\Create $table) {
                $table->binaryUniq('session_token', 32);
                $table->int('user_id');
                $table->binary('token_hash', 64);
                $table->int('created');
                $table->int('expires');
                $table->binary('device_token', 32)->nullable();
                $table->int('login_ip')->nullable();
                $table->string('login_user_agent', 255)->nullable();
                $table->int('last_activity');
                $table->primaryKey('session_token');
                $table->index('token_hash');
            });
        }

        if (!$schema->tableExists('xf_vanta_device')) {
            $schema->createTable('xf_vanta_device', function (\XF\Db\Schema\Create $table) {
                $table->int('device_id', 10)->autoIncrement();
                $table->int('user_id');
                $table->binary('hwid_hash', 64);
                $table->string('device_name', 255);
                $table->int('created');
                $table->int('last_seen');
                $table->string('create_ip', 45)->nullable();
                $table->primaryKey('device_id');
                $table->index('user_id');
                $table->index('hwid_hash');
                $table->unique(['user_id', 'hwid_hash']);
            });
        }

        if (!$schema->tableExists('xf_vanta_ban')) {
            $schema->createTable('xf_vanta_ban', function (\XF\Db\Schema\Create $table) {
                $table->int('ban_id', 10)->autoIncrement();
                $table->int('user_id')->nullable();
                $table->binary('hwid_hash', 64)->nullable();
                $table->string('reason', 255);
                $table->int('created_by');
                $table->string('created_by_username', 50);
                $table->int('created');
                $table->int('expires')->nullable();
                $table->string('type', 20);
                $table->primaryKey('ban_id');
                $table->index('user_id');
                $table->index('hwid_hash');
            });
        }

        if (!$schema->tableExists('xf_vanta_maint')) {
            $schema->createTable('xf_vanta_maint', function (\XF\Db\Schema\Create $table) {
                $table->string('setting_key', 50);
                $table->string('setting_value', 255);
                $table->primaryKey('setting_key');
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

        if (!$schema->tableExists('xf_vanta_log')) {
            $schema->createTable('xf_vanta_log', function (\XF\Db\Schema\Create $table) {
                $table->int('log_id', 10)->autoIncrement();
                $table->int('user_id')->nullable();
                $table->binary('hwid_hash', 64)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('event_type', 50);
                $table->string('detail', 255)->nullable();
                $table->int('timestamp');
                $table->primaryKey('log_id');
                $table->index('user_id');
                $table->index('event_type');
            });
        }

        if (!$schema->tableExists('xf_vanta_reset')) {
            $schema->createTable('xf_vanta_reset', function (\XF\Db\Schema\Create $table) {
                $table->int('reset_id', 10)->autoIncrement();
                $table->int('user_id');
                $table->binary('hwid_hash', 64);
                $table->string('reason', 255);
                $table->int('requested_by');
                $table->string('requested_by_username', 50);
                $table->int('approved_by')->nullable();
                $table->string('approved_by_username', 50)->nullable();
                $table->int('created');
                $table->int('processed')->nullable();
                $table->string('status', 20)->default('pending');
                $table->primaryKey('reset_id');
                $table->index('user_id');
                $table->index('status');
            });
        }
    }

    protected function schemaDrop()
    {
        $schema = $this->app->db()->schema();
        $schema->dropTable('xf_vanta_session');
        $schema->dropTable('xf_vanta_device');
        $schema->dropTable('xf_vanta_ban');
        $schema->dropTable('xf_vanta_maint');
        $schema->dropTable('xf_vanta_log');
        $schema->dropTable('xf_vanta_reset');
    }
}