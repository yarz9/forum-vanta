<?php

namespace XenSoluce\InviteSystem;

use XenSoluce\InviteSystem\Entity\CodeInvitation;
use XenSoluce\InviteSystem\Install\Install;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;

/**
 * Class Setup
 * @package XenSoluce\InviteSystem
 */
class Setup extends Install
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;


    /**Version : 2.1.0*/
    public function upgrade2010000Step1()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_xs_is_token', function(Alter $table)
        {
            $table->renameColumn('user_group_id', 'user');
            $table->addColumn('type_token', 'int');
            $table->addColumn('number_use', 'int');
        });
        $sm->alterTable('xf_xs_is_code_invitation', function(Alter $table)
        {
            $table->addColumn('token', 'varchar',32);
        });
        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('xs_is_invite_count', 'int')->setDefault(0);
        });
    }

    /**Version : 2.1.3*/
    public function upgrade2010300Step1()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_xs_is_code_invitation', function(Alter $table)
        {
            $table->addColumn('type_code', 'int')->setDefault(1);
        });

    }
    /**Version : 2.1.5*/
    public function upgrade2010500Step1()
    {
        $this->installByTable('xf_xs_is_personalized_invitation_code');
    }
    /**Version : 2.1.5 Fix 1*/
    public function upgrade2010510Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_xs_is_personalized_invitation_code', function(Alter $table)
        {
            $table->changeColumn('limit_use', 'int', 11)->unsigned('');
            $table->changeColumn('limit_time', 'int', 11)->unsigned('');
        });
    }
    /**Version : 2.1.5 Fix 2*/
    public function upgrade2010520Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->changeColumn('xs_is_invite_count')->setDefault(0);
        });
    }
    /**Version : 2.1.7 Fix 1*/
    public function upgrade2010710Step1()
    {
        $this->installByTable('xf_xs_is_user_group_code');
    }

    /**Version : 2.1.7 Fix 2*/
    public function upgrade2010720Step1()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_xs_is_token', function(Alter $table)
        {
            $table->addColumn('enable_add_user_group', 'tinyint', 3);
            $table->addColumn('type_user_group', 'enum')->values(['first', 'secondary', 'all'])->setDefault('secondary');
            $table->addColumn('user_group', 'int');
            $table->addColumn('secondary_user_group', 'varbinary', 255);
        });
    }

    /**Version : 2.2.0 */
    public function upgrade2020000Step1()
    {
       $this->installByTable('xf_xs_is_invitation_email');
    }

    /**Version : 2.3.0 */

    /**
     * @throws \XF\Db\Exception
     */
    public function upgrade2030000Step1()
    {
        $this->installByTables([
            'xf_xs_is_invitation_carts',
            'xf_xs_is_invitation_buy',
            'xf_xs_is_register_user'
        ]);
        $this->executeQuery();

        $codesInvitation = $this->app->finder('XenSoluce\InviteSystem:CodeInvitation')
            ->where('registered_user_id', '!=', '0'
            )->order('invitation_date', 'desc')->fetch();

        $insert = "INSERT INTO `xf_xs_is_register_user`(`by_user_id`, `registered_user_id`, `invitation_date`, `code_id`, `table_name`) VALUES ";

        $valueInsert = [];
        /** @var CodeInvitation $item */
        foreach ($codesInvitation as $item)
        {
            $valueInsert[] = "($item->user_id, $item->registered_user_id, $item->invitation_date, $item->code_id, 'XenSoluce\InviteSystem:CodeInvitation')";
        }

        $this->db()->query($insert . implode(', ', $valueInsert));
    }
}