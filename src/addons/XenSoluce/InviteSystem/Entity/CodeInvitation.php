<?php

namespace XenSoluce\InviteSystem\Entity;

use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * COLUMNS
 * @property int code_id
 * @property string code
 * @property string user_id
 * @property string token_id
 * @property int registered_user_id
 * @property int invitation_date
 * @property string token
 * @property string type_code
 *
 * RELATIONS
 * @property \XF\Entity\User RegisteredUser
 * @property \XenSoluce\InviteSystem\Entity\Token Token
 * @property \XF\Entity\User User
 */
class CodeInvitation extends Entity
{
    protected function _preSave()
    {
        if(empty($this->code))
        {
            $this->code = \XF::generateRandomString(32);
        }
        if(empty($this->invitation_date))
        {
            $this->invitation_date = \XF::$time;
        }
    }
    protected function _postDelete()
    {
        $db = $this->db();
        $db->delete('xf_xs_is_user_group_code', 'entity_id = ?', $this->code_id);
    }
    public static function getStructure(Structure $structure)
    {
        $structure->table      = 'xf_xs_is_code_invitation';
        $structure->shortName  = 'XenSoluce\InviteSystem:CodeInvitation';
        $structure->primaryKey = 'code_id';

        $structure->columns = [
            'code_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'code' => ['type' => self::STR, 'required' => true, 'maxLength' => 32],
            'user_id' => ['type' => self::STR, 'required' => true],
            'token_id' => ['type' => self::STR, 'required' => true, 'maxLength' => 32],
            'registered_user_id' => ['type' => self::UINT, 'default' => 0],
            'invitation_date'    => ['type' => self::UINT],
            'token' => ['type' => self::STR, 'maxLength' => 32],
            'type_code' => ['type' => self::STR, 'required' => true, 'default' => 1]
        ];
        $structure->relations = [
            'RegisteredUser' => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => [['user_id', '=', '$registered_user_id']]
            ],
            'Token' => [
                'entity'     => 'XenSoluce\InviteSystem:Token',
                'type'       => self::TO_ONE,
                'conditions' => 'token_id',
                'primary' => true
            ],
            'User' => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
        ];

        return $structure;
    }
}