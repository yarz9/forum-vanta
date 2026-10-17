<?php
/*************************************************************************
 * Invite System - Xen-Soluce (c) 2019-2023
 * All Rights Reserved.
 * Created by SyTy and CRUEL-MODZ
 *************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at https://xen-soluce.com/help/license-agreement/.
 *************************************************************************/

namespace XenSoluce\InviteSystem\Install;

use XF\Db\Schema\Create;

class Install extends AbstractInstall
{
    /**
     * @return array
     */
    protected function getTables(): array
    {
        $table = [];
        $table['xf_xs_is_ban'] = function ($table)
        {
            $table->addColumn('user_id', 'int');
            $table->addColumn('ban_user_id', 'int');
            $table->addColumn('ban_date', 'int')->setDefault(0);
            $table->addColumn('end_date', 'int')->setDefault(0);
            $table->addColumn('ban_reason', 'varchar', 255);
            $table->addPrimaryKey('user_id');
        };

        $table['xf_xs_is_code_invitation'] = function ($table)
        {
            $table->addColumn('code_id', 'int')->autoIncrement();
            $table->addColumn('code', 'varchar',32);
            $table->addColumn('user_id', 'int');
            $table->addColumn('token_id', 'int');
            $table->addColumn('token', 'varchar',32);
            $table->addColumn('registered_user_id', 'int');
            $table->addColumn('invitation_date', 'int');
            $table->addColumn('type_code', 'int')->setDefault(1);
            $table->addPrimaryKey('code_id');
        };

        $table['xf_xs_is_personalized_invitation_code'] = function ($table)
        {
            $table->addColumn('ic_personalize_id', 'int')->autoIncrement();
            $table->addColumn('title', 'varchar',50);
            $table->addColumn('code', 'varchar',32);
            $table->addColumn('limit_use', 'int', 11)->unsigned('');
            $table->addColumn('limit_time', 'int', 11)->unsigned('');
            $table->addColumn('registered_user_id', 'varbinary',255);
            $table->addColumn('invitation_date', 'int');
            $table->addColumn('enable', 'tinyint', 3);
            $table->addPrimaryKey('ic_personalize_id');
        };

        $table['xf_xs_is_token'] = function ($table)
        {
            $table->addColumn('token_id', 'int')->autoIncrement();
            $table->addColumn('title', 'varchar',100);
            $table->addColumn('token', 'varchar',32);
            $table->addColumn('type_token', 'int');
            $table->addColumn('user', 'varbinary',255);
            $table->addColumn('enable_add_user_group', 'tinyint', 3);
            $table->addColumn('type_user_group', 'enum')->values(['first', 'secondary', 'all'])->setDefault('secondary');
            $table->addColumn('user_group', 'int');
            $table->addColumn('secondary_user_group', 'varbinary', 255);
            $table->addColumn('number_use', 'int');
            $table->addPrimaryKey('token_id');
        };

        $table['xf_xs_is_user_group_code'] = function ($table)
        {
            $table->addColumn('group_code_id', 'int')->autoIncrement();
            $table->addColumn('code', 'varchar', 32);
            $table->addColumn('entity_id', 'int');
            $table->addColumn('max_invite', 'int', 11)->unsigned('');
            $table->addColumn('type_user_group', 'enum')->values(['first', 'secondary', 'all'])->setDefault('secondary');
            $table->addColumn('user_group', 'int');
            $table->addColumn('secondary_user_group', 'varbinary', 255);
            $table->addPrimaryKey('group_code_id');
        };

        $table['xf_xs_is_invitation_email'] = function ($table)
        {
            $table->addColumn('invitation_email_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('code', 'varchar', 32);
            $table->addColumn('code_id', 'int');
            $table->addColumn('subject', 'varchar', 255);
            $table->addColumn('message', 'text');
            $table->addColumn('email', 'varchar', 255);
            $table->addColumn('is_admin', 'tinyint');
            $table->addPrimaryKey('invitation_email_id');
        };

        $table['xf_xs_is_invitation_carts'] = function ($table)
        {
            $table->addColumn('invitation_cart_id', 'int')->autoIncrement();
            $table->addColumn('number', 'int');
            $table->addColumn('price', 'decimal', '10,2');
            $table->addColumn('currency', 'varchar', 4);
            $table->addColumn('user_id', 'int');
            $table->addColumn('email', 'varchar', 120)->setDefault('');
            $table->addColumn('state', 'enum')->values(['no', 'pending', 'validate', 'refund'])
                ->setDefault('no');
            $table->addColumn('buy_type', 'enum')->values(['login', 'register'])->setDefault('login');
            $table->addColumn('cart_date', 'int');
            $table->addColumn('extra_data', 'mediumblob');
            $table->addColumn('purchase_request_key', 'varbinary', 32)->nullable();
            $table->addPrimaryKey('invitation_cart_id');
        };

        $table['xf_xs_is_invitation_buy'] = function ($table)
        {
            $table->addColumn('invitation_buy_id', 'int')->autoIncrement();
            $table->addColumn('code', 'varchar', 35);
            $table->addColumn('invitation_cart_id', 'int');
            $table->addColumn('registered_user_id', 'varchar', 255);
            $table->addPrimaryKey('invitation_buy_id');
        };

        $table['xf_xs_is_register_user'] = function ($table)
        {
            $table->addColumn('register_user_id', 'int')->autoIncrement();
            $table->addColumn('by_user_id', 'int');
            $table->addColumn('registered_user_id', 'int');
            $table->addColumn('invitation_date', 'int');
            $table->addColumn('code_id', 'int');
            $table->addColumn('table_name', 'varchar', 255);
            $table->addPrimaryKey('register_user_id');
        };

        return $table;
    }


    /**
     * @return array
     */
    protected function getAlterDefinitions(): array
    {
        $definitions = [];

        $definitions['xf_user'] = [
            'columns' => [
                'xs_is_invite_count'   => [
                    'type'    => 'int',
                    'default' => 0
                ],
            ],
        ];

        return $definitions;
    }

    /**
     * @return string
     */
    protected function getQuery(): string
    {
        return "REPLACE INTO xf_purchasable
					(purchasable_type_id, purchasable_class, addon_id)
				VALUES
					('xs_invite_system', 'XenSoluce\\\\InviteSystem:Invitation', 'XenSoluce/InviteSystem')";
    }

    protected function getQueryUninstall(): string
    {
        return "DELETE FROM `xf_purchasable` WHERE purchasable_type_id = 'xs_invite_system';";
    }
}