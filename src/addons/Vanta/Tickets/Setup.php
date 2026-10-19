<?php

namespace Vanta\Tickets;

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
        $this->applyDefaultPermissions();
    }

    public function upgrade(array $stepParams = [], &$previousVersion = false)
    {
        $this->schemaCreate();
        $this->applyDefaultPermissions();
    }

    public function uninstall(array $stepParams = [])
    {
        $this->schemaDrop();
    }

    protected function schemaCreate()
    {
        $sm = $this->app->db()->getSchemaManager();

        if (!$sm->tableExists('xf_vanta_ticket')) {
            $sm->createTable('xf_vanta_ticket', function (\XF\Db\Schema\Create $table) {
                $table->addColumn('ticket_id', 'int')->autoIncrement();
                $table->addColumn('user_id', 'int');
                $table->addColumn('username', 'varchar', 50);
                $table->addColumn('ticket_type', 'varchar', 20)->setDefault('general');
                $table->addColumn('title', 'varchar', 255);
                $table->addColumn('status', 'varchar', 20)->setDefault('open');
                $table->addColumn('hwid', 'varchar', 64)->nullable();
                $table->addColumn('hwid_hash', 'varbinary', 64)->nullable();
                $table->addColumn('message_count', 'int')->setDefault(0);
                $table->addColumn('created', 'int');
                $table->addColumn('last_activity', 'int');
                $table->addPrimaryKey('ticket_id');
                $table->addKey('user_id');
                $table->addKey('status');
                $table->addKey('last_activity');
            });
        }

        if (!$sm->tableExists('xf_vanta_ticket_message')) {
            $sm->createTable('xf_vanta_ticket_message', function (\XF\Db\Schema\Create $table) {
                $table->addColumn('message_id', 'int')->autoIncrement();
                $table->addColumn('ticket_id', 'int');
                $table->addColumn('user_id', 'int');
                $table->addColumn('username', 'varchar', 50);
                $table->addColumn('is_staff', 'tinyint')->setDefault(0);
                $table->addColumn('message', 'text');
                $table->addColumn('created', 'int');
                $table->addPrimaryKey('message_id');
                $table->addKey('ticket_id');
            });
        }
    }

    protected function applyDefaultPermissions()
    {
        $db = $this->app->db();

        $existed = (int) $db->fetchOne("
            SELECT COUNT(*)
            FROM xf_permission_entry
            WHERE permission_group_id = 'VantaTickets'
        ");

        if ($existed)
        {
            return;
        }

        $rows = [
            [2, 'vanta_tickets_view', 1],
            [2, 'vanta_tickets_create', 1],
            [3, 'vanta_tickets_view', 1],
            [3, 'vanta_tickets_create', 1],
            [3, 'vanta_tickets_staff', 1],
        ];

        foreach ($rows AS [$groupId, $permissionId, $value])
        {
            $db->insert('xf_permission_entry', [
                'user_group_id' => $groupId,
                'user_id' => 0,
                'permission_group_id' => 'VantaTickets',
                'permission_id' => $permissionId,
                'permission_value' => $value ? 'allow' : 'deny',
                'permission_value_int' => $value,
            ]);
        }
    }

    protected function schemaDrop()
    {
        $sm = $this->app->db()->getSchemaManager();
        $sm->dropTable('xf_vanta_ticket_message');
        $sm->dropTable('xf_vanta_ticket');
    }
}
