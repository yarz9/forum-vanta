<?php

namespace XenSoluce\InviteSystem\Entity;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;
use XF\Phrase;


/**
 * COLUMNS
 * @property int $invitation_cart_id
 * @property int $number
 * @property int $user_id
 * @property int email
 * @property float $price
 * @property string $currency
 * @property string $state
 * @property string buy_type
 * @property int $cart_date
 * @property array $extra_data
 * @property string|null $purchase_request_key
 *
 * GETTERS
 * @property Phrase $title
 * @property Phrase $description
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property AbstractCollection|InvitationBuy[] $InvitationBuy
 */
class InvitationCarts extends Entity
{
    /**
     * @return Phrase
     */
    public function getTitle(): Phrase
    {
        return \XF::phrase('xs_is_purchase_of_x_invitation_codes', [
            'number' => $this->number
        ]);
    }

    /**
     * @return Phrase
     */
    public function getDescription(): Phrase
    {
        if($this->buy_type === 'register') {
            return \XF::phrase('xs_is_a_user_buy_an_invitation_code');
        }

        return \XF::phrase('xs_is_the_user_x_to_buy_number_invitation_code', [
            'user' => $this->User->username,
            'number' => $this->number
        ]);
    }

    /**
     * @return float|int
     */
    public function getCost()
    {
        return $this->number * $this->price;
    }

    /**
     * @return string|Phrase
     */
    public function getState()
    {

        switch ($this->state) {
            case 'no':
                return \XF::phrase('xs_is_awaiting_payment');
            case 'pending':
                return \XF::phrase('xs_is_pending_validation');
            case 'validate':
                return \XF::phrase('xs_is_validate');
            case 'refund':
                return \XF::phrase('xs_is_cancelled');
            default: return '';
        }

//        return match ($this->state) {
//            'no' => \XF::phrase('xs_is_awaiting_payment'),
//            'pending' => \XF::phrase('xs_is_pending_validation'),
//            'validate' => \XF::phrase('xs_is_validate'),
//            'refund' => \XF::phrase('xs_is_cancelled'),
//            default => '',
//        };
    }

    /**
     * @return mixed
     */
    public function getCostPhrase(): mixed
    {
        return $this->app()->data('XF:Currency')->languageFormat($this->getCost(), $this->currency);
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table      = 'xf_xs_is_invitation_carts';
        $structure->shortName  = 'XenSoluce\InviteSystem:InvitationCarts';
        $structure->primaryKey = 'invitation_cart_id';

        $structure->columns = [
            'invitation_cart_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'number' => ['type' => self::UINT, 'default' => 1],
            'user_id' => ['type' => self::UINT, 'default' => \XF::visitor()->user_id],
            'email' => ['type' => self::STR, 'maxLength' => 120, 'default' => ''],
            'price' => ['type' => self::FLOAT, 'default' => \XF::options()->xs_is_code_buy['price']],
            'currency' => ['type' => self::STR, 'default' => \XF::options()->xs_is_code_buy['currency']],
            'state' => ['type' => self::STR, 'default' => 'no',
                'allowedValues' => ['no', 'pending', 'validate', 'refund']
            ],
            'buy_type' => ['type' => self::STR, 'default' => 'login',
                'allowedValues' => ['login', 'register']
            ],
            'cart_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'extra_data' => ['type' => self::JSON_ARRAY, 'default' => []],
            'purchase_request_key' => ['type' => self::STR, 'maxLength' => 32, 'nullable' => true],
        ];

        $structure->getters = [
            'title' => true,
            'description' => true,
            'cost_phrase' => true
        ];

        $structure->relations = [
            'User' => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'InvitationBuy' => [
                'entity'     => 'XenSoluce\InviteSystem:InvitationBuy',
                'type'       => self::TO_MANY,
                'conditions' => 'invitation_cart_id',
                'fetch' => true
            ],
        ];

        return $structure;
    }
}