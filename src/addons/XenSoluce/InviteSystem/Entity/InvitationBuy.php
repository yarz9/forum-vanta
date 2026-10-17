<?php

namespace XenSoluce\InviteSystem\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * COLUMNS
 * @property int $invitation_buy_id
 * @property int $user_id
 * @property string $code
 * @property int $invitation_cart_id
 * @property int $registered_user_id
 *
 * RELATIONS
 * @property InvitationCarts $InvitationCart
 * @property User $RegisteredUser
 */
class InvitationBuy extends Entity
{
    protected function _preSave()
    {
        if(empty($this->code))
        {
            $this->code = 'buy_' . \XF::generateRandomString(30);
        }
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table      = 'xf_xs_is_invitation_buy';
        $structure->shortName  = 'XenSoluce\InviteSystem:InvitationBuy';
        $structure->primaryKey = 'invitation_buy_id';

        $structure->columns = [
            'invitation_buy_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'code' => ['type' => self::STR, 'required' => true, 'maxLength' => 35],
            'invitation_cart_id' => ['type' => self::INT],
            'registered_user_id' => ['type' => self::UINT, 'default' => 0],
        ];

        $structure->relations = [
            'InvitationCart' => [
                'entity'     => 'XenSoluce\InviteSystem:InvitationCarts',
                'type'       => self::TO_ONE,
                'conditions' => 'invitation_cart_id',
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