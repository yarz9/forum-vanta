<?php

namespace XenSoluce\InviteSystem\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * COLUMNS
 * @property int group_code_id
 * @property string code
 * @property int entity_id
 * @property int max_invite
 * @property string type_user_group
 * @property int user_group
 * @property array secondary_user_group
 *
 * RELATIONS
 * @property \XenSoluce\InviteSystem\Entity\CodeInvitation Code
 * @property \XenSoluce\InviteSystem\Entity\PersonalizedInvitationCode CodeCustom
 */

class UserGroupCode extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table      = 'xf_xs_is_user_group_code';
        $structure->shortName  = 'XenSoluce\InviteSystem:UserGroupCode';
        $structure->primaryKey = 'group_code_id';

        $structure->columns = [
            'group_code_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'code' => ['type' => self::STR, 'required' => true, 'maxLength' => 32],
            'entity_id' => ['type' => self::UINT],
            'max_invite' => ['type' => self::INT],
            'type_user_group' => ['type' => self::STR, 'default' => 'secondary',
                'allowedValues' => ['first', 'secondary', 'all']
            ],
            'user_group' => ['type' => self::UINT],
            'secondary_user_group' => ['type' => self::LIST_COMMA]
        ];
        $structure->relations = [
            'Code' => [
                'entity'     => 'XenSoluce\InviteSystem:CodeInvitation',
                'type'       => self::TO_ONE,
                'conditions' => [['code_id', '=', '$entity_id']],
            ],
            'CodeCustom' => [
                'entity'     => 'XenSoluce\InviteSystem:PersonalizedInvitationCode',
                'type'       => self::TO_ONE,
                'conditions' => [['ic_personalize_id', '=', '$entity_id']],
            ],
        ];

        return $structure;
    }
}