<?php

namespace XenSoluce\InviteSystem\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * COLUMNS
 * @property int user_id
 * @property int ban_user_id
 * @property int ban_date
 * @property int end_date
 * @property string ban_reason
 *
 * RELATIONS
 * @property \XF\Entity\User User
 * @property \XF\Entity\User BanUser
 */
class Banning extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table      = 'xf_xs_is_ban';
        $structure->shortName  = 'XenSoluce\InviteSystem:Banning';
        $structure->primaryKey = 'user_id';

        $structure->columns = [
            'user_id' => ['type' => self::UINT, 'required' => true,
                'unique' => 'this_user_is_already_banned'
            ],
            'ban_user_id' => ['type' => self::UINT, 'required' => true],
            'ban_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'end_date' => ['type' => self::UINT, 'required' => true],
            'ban_reason' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
        ];

        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'BanUser' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['user_id', '=', '$ban_user_id']
                ],
                'primary' => true
            ]
        ];

        return $structure;
    }
}