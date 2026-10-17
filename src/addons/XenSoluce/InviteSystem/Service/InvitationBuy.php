<?php

namespace XenSoluce\InviteSystem\Service;

use XenSoluce\InviteSystem\Entity\CodeInvitation;
use XenSoluce\InviteSystem\Entity\InvitationCarts;
use XF\App;
use XF\Entity\User;
use XF\Payment\CallbackState;
class InvitationBuy extends \XF\Service\AbstractService
{
    /**
     * @var InvitationCarts|null
     */
    protected $cart = null;

    /**
     * @var User|null
     */
    protected $purchaser = null;

    /**
     * @var array
     */
    protected $extraData = [];

    /**
     * @var CallbackState
     */
    protected $state;

    /**
     * InvitationBuy constructor.
     * @param App $app
     * @param InvitationCarts $cart
     * @param User|null $purchaser
     */
    public function __construct(\XF\App $app, InvitationCarts $cart, ?User $purchaser)
    {
        parent::__construct($app);

        $this->cart = $cart;
        $this->purchaser = $purchaser;
    }

    /**
     * @param $extraData
     */
    public function setExtraData($extraData)
    {
        $this->extraData = $extraData;
    }

    /**
     * @param CallbackState $state
     */
    public function setState(CallbackState $state)
    {
        $this->state = $state;
    }

    /**
     * @throws \XF\PrintableException
     */
    public function save(): ?InvitationCarts
    {
        $cart = $this->cart;
        $invitationOutput = [];

        for($i = 1; $i <= $cart->number; ++$i)
        {
            /** @var \XenSoluce\InviteSystem\Entity\InvitationBuy $invitation */
            $invitation = $this->em()->create('XenSoluce\InviteSystem:InvitationBuy');
            $invitation->invitation_cart_id = $cart->invitation_cart_id;
            $invitation->save();

            $invitationOutput[] = $invitation->invitation_buy_id;
        }
        $cart->state = 'validate';
        $cart->purchase_request_key = $this->state->requestKey;
        $extra = $cart->extra_data;
        $extra['invitation_buy'] = $invitationOutput;
        $cart->extra_data = $extra;

        $cart->save();

        return $cart;
    }

    /**
     * @param bool $refund
     * @throws \XF\PrintableException
     */
    public function reverse(bool $refund = false)
    {
        $cart = $this->cart;

        if($refund)
        {
            foreach ($cart->InvitationBuy as $buy)
            {
                if($buy->registered_user_id)
                {
                    $buy->RegisteredUser->user_state = 'moderated';
                    $buy->RegisteredUser->save();
                }
            }
            $cart->state = 'refund';
            $cart->save();
        }
        else
        {
            foreach ($cart->InvitationBuy as $buy)
            {
                if($buy->registered_user_id)
                {
                    $buy->RegisteredUser->user_state = 'valid';
                    $buy->RegisteredUser->save();
                }
            }
            $cart->state = 'validate';
            $cart->save();
        }
    }
}