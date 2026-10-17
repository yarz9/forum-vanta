<?php

namespace XenSoluce\InviteSystem\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * COLUMNS
 * @property int token_id
 * @property string title
 * @property string token
 * @property array user
 * @property int type_token
 * @property bool enable_add_user_group
 * @property string type_user_group
 * @property int user_group
 * @property array secondary_user_group
 * @property int number_use
 *
 * RELATIONS
 * @property \XenSoluce\InviteSystem\Entity\CodeInvitation Code
 * @property \XF\Mvc\Entity\AbstractCollection|\XenSoluce\InviteSystem\Entity\CodeInvitation[] Codes
 */
class Token extends Entity
{
    public function canUse()
    {
        $visitor = \XF::visitor();
        $Codes = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
            ->where([
                'user_id'=> $visitor->user_id,
                'token_id' => $this->token_id
            ]);

        if($this->number_use === 1)
        {
            return empty($Codes->fetchOne());
        }
        elseif ($this->number_use > 1)
        {
            return !($Codes->total() > $this->number_use);
        }
        return true;
    }

    protected function _preSave()
    {
        if(empty($this->token))
        {
            $this->token = \XF::generateRandomString(32);
        }
    }
    protected function _postDelete()
    {
        $db = $this->db();
        foreach ($this->Codes as $code)
        {
            $db->delete('xf_xs_is_user_group_code', 'entity_id = ?', $code->code_id);
        }
    }
    public static function getStructure(Structure $structure)
    {
        $structure->table      = 'xf_xs_is_token';
        $structure->shortName  = 'XenSoluce\InviteSystem:Token';
        $structure->primaryKey = 'token_id';

        $structure->columns = [
            'token_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'title' => ['type' => self::STR, 'required' => true, 'maxLength' => 100],
            'token' => ['type' => self::STR, 'required' => true, 'maxLength' => 32],
            'user' => ['type' => self::LIST_COMMA],
            'type_token' => ['type' => self::UINT, 'required' => true],
            'enable_add_user_group' => ['type' => self::BOOL, 'default' => false],
            'type_user_group' => ['type' => self::STR, 'default' => 'secondary',
                'allowedValues' => ['first', 'secondary', 'all']
            ],
            'user_group' => ['type' => self::UINT,  'default' => 0],
            'secondary_user_group' => ['type' => self::JSON_ARRAY, 'default' => ''],
            'number_use' =>  ['type' => self::UINT, 'required' => true, 'default' => 1]
        ];
        $structure->relations = [
            'Code' => [
                'entity'     => 'XenSoluce\InviteSystem:CodeInvitation',
                'type'       => self::TO_ONE,
                'conditions' => 'token_id',
            ],
            'Codes' => [
                'entity'     => 'XenSoluce\InviteSystem:CodeInvitation',
                'type'       => self::TO_MANY,
                'conditions' => 'token_id',
            ],
        ];

        return $structure;
    }
}