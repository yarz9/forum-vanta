<?php

namespace XenSoluce\InviteSystem\Entity;

use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * COLUMNS
 * @property int invitation_email_id
 * @property int user_id
 * @property string code
 * @property int code_id
 * @property string subject
 * @property string message
 * @property string email
 * @property bool is_admin
 */
class InvitationEmail extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table      = 'xf_xs_is_invitation_email';
        $structure->shortName  = 'XenSoluce\InviteSystem:InvitationEmail';
        $structure->primaryKey = 'invitation_email_id';

        $structure->columns = [
            'invitation_email_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'default' => 0],
            'code' => ['type' => self::STR, 'maxLength' => 32],
            'code_id' => ['type' => self::UINT],
            'subject' => ['type' => self::STR, 'maxLength' => 255],
            'message' => ['type' => self::STR],
            'email'    => ['type' => self::STR],
            'is_admin' => ['type' => self::BOOL]
        ];

        return $structure;
    }
}