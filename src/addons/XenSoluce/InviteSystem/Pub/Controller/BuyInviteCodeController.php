<?php

namespace XenSoluce\InviteSystem\Pub\Controller;

use XenSoluce\InviteSystem\Entity\InvitationCarts;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
use XF\Repository\PaymentRepository;

class BuyInviteCodeController extends AbstractController
{
    public function actionIndex()
    {
        $option = \XF::options()->xs_is_code_buy_register;
        if(
            !$option['enable']
            || empty($option['payment_profile_ids'])
            || $option['price'] <= 0
            || \XF::visitor()->user_id
            || \XF::options()->xs_is_code_required['mandatory'] !== 'yes'
        ) {
            return $this->notFound();
        }

        $paymentRepo = $this->repository(PaymentRepository::class);
        $profiles = $paymentRepo->findPaymentProfilesForList()
            ->pluckFrom(function ($e) {
                return ($e->display_title ?: $e->Provider->title);
            })
            ->where('payment_profile_id', $option['payment_profile_ids'])
            ->fetch();

        $viewParams = [
            'profiles' => $profiles
        ];

        return $this->view('', 'xs_is_buy_invite_code', $viewParams);
    }

    public function actionComplete(ParameterBag $params)
    {
        $requestKey = $params->request_key ?: $this->filter('request_key', 'str');

        /** @var InvitationCarts $invitationCart */
        $invitationCart = $this->finder('XenSoluce\InviteSystem:InvitationCarts')
            ->where('purchase_request_key', '=', $requestKey)
            ->where('user_id', '=', 0)
            ->where('state', '=', 'validate')
            ->fetchOne();

        if ($invitationCart->InvitationBuy->count())
        {
            return $this->redirect($this->buildLink('register', null, [
                'invitation_code' => $invitationCart->InvitationBuy->first()->code
            ]));
        }

        return $this->view('', 'xs_is_payment_processing');
    }
}