<?php

namespace XenSoluce\InviteSystem\Purchasable;

use XenSoluce\InviteSystem\Entity\InvitationCarts;
use XenSoluce\InviteSystem\Service\InvitationBuy;
use XF\Entity\PaymentProfile;
use XF\Entity\User;
use XF\Http\Request;
use XF\Payment\CallbackState;
use XF\PrintableException;
use XF\Purchasable\AbstractPurchasable;
use XF\Purchasable\Purchase;

class Invitation extends AbstractPurchasable
{
    /**
     * @return mixed|\XF\Phrase
     */
    public function getTitle()
    {
        return \XF::phrase('xs_is_purchase_of_invitations_codes');
    }

    /**
     * @param Request $request
     * @param User $purchaser
     * @param null $error
     * @return bool|Purchase
     * @throws PrintableException
     */
    public function getPurchaseFromRequest(Request $request, User $purchaser, &$error = null)
    {
        $profileId = $request->filter('payment_profile_id', 'uint');

        /** @var PaymentProfile $paymentProfile */
        $paymentProfile = \XF::em()->find('XF:PaymentProfile', $profileId);
        if (!$paymentProfile || !$paymentProfile->active) {
            $error = \XF::phrase('please_choose_valid_payment_profile_to_continue_with_your_purchase');
            return false;
        }

        if (!$purchaser->user_id) {
            $email = $request->filter('email', 'str');

            /** @var \XF\Validator\Email $emailValidator */
            $emailValidator = \XF::app()->validator('Email');
            if (!$emailValidator->isValid($email))
            {
                $error = \XF::phrase('please_enter_valid_email');
                return false;
            }

            $em = \XF::em();

            /** @var \XF\Entity\User $user */
            $user = $em->findOne('XF:User', ['email' => $email]);
            if ($user)
            {
                $error = \XF::phrase('xs_is_user_with_this_email_is_already_registered');
                return false;
            }

            $carts = $em->getFinder('XenSoluce\InviteSystem:InvitationCarts');

            $carts->where('buy_type', 'register');
            $carts->where('state', ['validate', 'refund']);
            $carts->where('email', $email);

            if($carts->fetchOne()) {
                $error = \XF::phrase('xs_is_already_purchased_for_this_email');
                return false;
            }

            $carts->where('state', ['no', 'pending']);
            $carts = $carts->fetchOne();
            $number = 1;

            if(!$carts) {
                /** @var InvitationCarts $carts */
                $carts = $em->create('XenSoluce\InviteSystem:InvitationCarts');

                $carts->user_id = 0;
                $carts->buy_type = 'register';
                $carts->email = $email;
            }

            $options = \XF::options();
            $codeBuy = $options->xs_is_code_buy_register;

        } else {
            $number = $request->filter('number', 'int');
            if ($number < 0) {
                $error = \XF::phrase('xs_is_please_enter_a_number_above_0');
                return false;
            }

            if ($number > 50) {
                $error = \XF::phrase('xs_is_please_enter_a_number_less_than_x', [
                    'number' => 50
                ]);
                return false;
            }

            $em = \XF::em();

            /** @var InvitationCarts $carts */
            $carts = $em->getFinder('XenSoluce\InviteSystem:InvitationCarts')
                ->where([
                    'user_id' => $purchaser->user_id,
                    'state' => 'no'
                ])->fetchOne();
            if(empty($carts))
            {
                /** @var InvitationCarts $carts */
                $carts = $em->create('XenSoluce\InviteSystem:InvitationCarts');
                $carts->user_id = $purchaser->user_id;
            }

            $options = \XF::options();
            $codeBuy = $options->xs_is_code_buy;
        }

        $carts->currency = $codeBuy['currency'];
        $carts->price = $codeBuy['price'];
        $carts->number = $number;
        $carts->save();

        return $this->getPurchaseObject($paymentProfile, $carts, $purchaser);
    }

    /**
     * @param PaymentProfile $paymentProfile
     * @param InvitationCarts $purchasable
     * @param User $purchaser
     *
     * @return Purchase
     */
    public function getPurchaseObject(PaymentProfile $paymentProfile, $purchasable, User $purchaser)
    {
        $purchase = new Purchase();

        $requestKey = \XF::app()->request()->filter('request_key', 'str');

        $purchase->title = \XF::phrase('xs_is_buy_invitation_code') . ': ' . $purchasable->title . ' (' . (!$purchaser->user_id ?  $purchasable->invitation_cart_id . ' - ' .  $purchasable->email : $purchaser->username) . ')';
        $purchase->description = $purchasable->description;
        $purchase->cost = $purchasable->getCost() ;
        $purchase->currency = $purchasable->currency;

        $purchase->purchaser = $purchaser;
        $purchase->paymentProfile = $paymentProfile;
        $purchase->purchasableTypeId = $this->purchasableTypeId;
        $purchase->purchasableId = $purchasable->invitation_cart_id;
        $purchase->purchasableTitle = $purchasable->title;
        $purchase->extraData = [
            'number' => $purchasable->number,
            'price' => $purchasable->price,
            'cart_id' => $purchasable->invitation_cart_id,
            'requestKey' => $requestKey
        ];

        $router = \XF::app()->router('public');

        if(!$purchaser->user_id) {
            $purchase->returnUrl = $router->buildLink('canonical:buy-invite-code/complete', null, ['request_key' => $requestKey]);
            $purchase->cancelUrl = $router->buildLink('canonical:register');
        } else {
            $purchase->returnUrl = $router->buildLink('canonical:account/invitation-buy/complete', null, ['invitation_cart_id' => $purchasable->invitation_cart_id]);
            $purchase->cancelUrl = $router->buildLink('canonical:account/invitation-buy');
        }

        return $purchase;
    }

    /**
     * @param array $extraData
     * @param PaymentProfile $paymentProfile
     * @param User $purchaser
     * @param null $error
     * @return bool|Purchase
     */
    public function getPurchaseFromExtraData(array $extraData, PaymentProfile $paymentProfile, User $purchaser, &$error = null)
    {
        $purchasable = $this->getPurchasableFromExtraData($extraData);
        if (!$purchasable['purchasable']) {
            $error = \XF::phrase('this_item_cannot_be_purchased_at_moment');
            return false;
        }

        if (!in_array($paymentProfile->payment_profile_id, \XF::options()->xs_is_code_buy['payment_profile_ids'])) {
            $error = \XF::phrase('selected_payment_profile_is_not_valid_for_this_purchase');
            return false;
        }

        return $this->getPurchaseObject($paymentProfile, $purchasable['purchasable'], $purchaser);
    }

    /**
     * @param array $extraData
     * @return array|mixed
     */
    public function getPurchasableFromExtraData(array $extraData)
    {
        $output = [
            'link' => '',
            'title' => '',
            'purchasable' => null
        ];

        /** @var InvitationCarts $cart */
        $cart = \XF::em()->find('XenSoluce\InviteSystem:InvitationCarts', $extraData['cart_id']);
        if ($cart) {
            $output['link'] = \XF::app()->router('admin')->buildLink('invitation/logs/orders', $cart);
            $output['title'] = $cart->title;
            $output['purchasable'] = $cart;
        }

        return $output;
    }

    /**
     * @param CallbackState $state
     * @return mixed|void
     * @throws PrintableException
     */
    public function completePurchase(CallbackState $state)
    {
        $purchaseRequest = $state->getPurchaseRequest();

        $cartId = $purchaseRequest->extra_data['cart_id'];

        $paymentResult = $state->paymentResult;
        $purchaser = $state->getPurchaser();
        if (!$purchaser) {
            $purchaser = null;
        }

        $cart = \XF::em()->find(
            'XenSoluce\InviteSystem:InvitationCarts',
            $cartId
        );

        /** @var InvitationBuy $invitationService */
        $invitationService = \XF::app()->service('XenSoluce\InviteSystem:InvitationBuy', $cart, $purchaser);

        if ($purchaseRequest->extra_data) {
            $invitationService->setExtraData($purchaseRequest->extra_data);
        }

        $cart = false;
        switch ($paymentResult)
        {
            case CallbackState::PAYMENT_RECEIVED:
                $invitationService->setState($state);
                /** @var InvitationCarts $cart */
                $cart = $invitationService->save();

                $state->logType = 'payment';
                $state->logMessage = 'Payment received, invitation code logged.';
                break;

            case CallbackState::PAYMENT_REINSTATED:
                if(isset($purchaseRequest->extra_data['invitation_cart_id']))
                {
                    $invitationService->reverse();

                    $state->logType = 'payment';
                    $state->logMessage = 'Reversal cancelled, invitation code reactivated.';
                }
                else
                {
                    $state->logType = 'info';
                    $state->logMessage = 'OK, no action.';
                }
                break;
        }

        if ($cart && $purchaseRequest) {
            $extraData = $purchaseRequest->extra_data;
            $extraData['invitation_cart_id'] = $cart->invitation_cart_id;
            $purchaseRequest->extra_data = $extraData;
            $purchaseRequest->save();
        }
    }

    /**
     * @param CallbackState $state
     * @return mixed|void
     * @throws PrintableException
     */
    public function reversePurchase(CallbackState $state)
    {
        $purchaseRequest = $state->getPurchaseRequest();

        $cartId = $purchaseRequest->extra_data['cart_id'];

        $purchaser = $state->getPurchaser();

        $cart = \XF::em()->find(
            'XenSoluce\InviteSystem:InvitationCarts',
            $cartId
        );

        /** @var InvitationBuy $invitationService */
        $invitationService = \XF::app()->service('XenSoluce\InviteSystem:InvitationBuy', $cart, $purchaser);

        $invitationService->reverse(true);

        $state->logType = 'cancel';
        $state->logMessage = 'Payment refunded/reversed, downgraded.';
    }

    /**
     * @param CallbackState $state
     * @param null $error
     * @return bool
     */
    public function validatePurchaser(CallbackState $state, &$error = null)
    {
        $purchaseRequest = $state->getPurchaseRequest();
        $purchasable = $this->getPurchasableFromExtraData($purchaseRequest->extra_data);
        if ($purchasable['purchasable']->buy_type === 'login' && $state->getPurchaseRequest()->user_id && !$state->getPurchaser()) {
            $error = 'Could not find user with user_id ' . $state->getPurchaseRequest()->user_id . '.';

            return false;
        }

        return true;
    }

    /**
     * @param $profileId
     * @return array|void
     */
    public function getPurchasablesByProfileId($profileId)
    {
    }

    /**
     * @param CallbackState $state
     */
    public function sendPaymentReceipt(CallbackState $state)
    {
        if ($state->paymentResult == CallbackState::PAYMENT_RECEIVED)
        {
            $purchaseRequest = $state->getPurchaseRequest();
            if ($purchaseRequest)
            {
                $purchasable = $this->getPurchasableFromExtraData($purchaseRequest->extra_data);

                $params = [
                    'purchaseRequest' => $purchaseRequest,
                    'purchasable' => $purchasable
                ];

                $mail = \XF::app()->mailer()->newMail();
                if ($purchasable['purchasable']->buy_type === 'login')
                {
                    $mail->setToUser($state->purchaser);
                }
                else
                {
                    $mail->setTo($purchasable['purchasable']->email);
                }

                $mail->setTemplate('payment_received_receipt_' . $this->purchasableTypeId, $params)
                    ->send();
            }
        }
    }
}
