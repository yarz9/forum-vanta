<?php

namespace XenSoluce\InviteSystem\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * COLUMNS
 * @property int $register_user_id
 * @property int $by_user_id
 * @property int $registered_user_id
 * @property int $invitation_date
 * @property int $code_id
 * @property string $table_name
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \XF\Entity\User $RegisteredUser
 */
class RegisterUser extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table      = 'xf_xs_is_register_user';
        $structure->shortName  = 'XenSoluce\InviteSystem:RegisterUser';
        $structure->primaryKey = 'register_user_id';

        $structure->columns = [
            'register_user_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'by_user_id' => ['type' => self::UINT, 'required' => true],
            'registered_user_id' => ['type' => self::UINT, 'default' => \XF::visitor()->user_id],
            'invitation_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'code_id' => ['type' => self::UINT, 'required' => true],
            'table_name' => ['type' => self::STR, 'required' => true],
        ];

        $structure->relations = [
            'User' => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => [['user_id', '=', '$by_user_id']],
                'primary' => true
            ],
            'RegisteredUser' => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => [['user_id', '=', '$registered_user_id']]
            ],
        ];

        return $structure;
    }
}