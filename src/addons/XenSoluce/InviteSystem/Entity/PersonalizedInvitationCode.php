<?php

namespace XenSoluce\InviteSystem\Entity;

use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * COLUMNS
 * @property int ic_personalize_id
 * @property string title
 * @property string code
 * @property int limit_use
 * @property int limit_time
 * @property array registered_user_id
 * @property int invitation_date
 * @property bool enable
 */
class PersonalizedInvitationCode extends Entity
{
    protected function _postDelete()
    {
        $db = $this->db();
        $db->delete('xf_xs_is_user_group_code', 'entity_id = ?', $this->ic_personalize_id);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table      = 'xf_xs_is_personalized_invitation_code';
        $structure->shortName  = 'XenSoluce\InviteSystem:PersonalizedInvitationCode';
        $structure->primaryKey = 'ic_personalize_id';

        $structure->columns = [
            'ic_personalize_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'title' => ['type' => self::STR, 'required' => true, 'maxLength' => 50],
            'code' => ['type' => self::STR, 'required' => true, 'maxLength' => 32],
            'limit_use' => ['type' => self::INT, 'default' => -1],
            'limit_time' => ['type' => self::INT, 'default' => -1],
            'registered_user_id' => ['type' => self::LIST_COMMA, 'default' => []],
            'invitation_date'    => ['type' => self::UINT],
            'enable' => ['type' => self::BOOL, 'default' => true]
        ];

        return $structure;
    }
}