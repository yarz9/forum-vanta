<?php

namespace XenSoluce\InviteSystem\XF\Pub\Controller;

use XenSoluce\InviteSystem\Entity\InvitationCarts;
use XenSoluce\InviteSystem\Entity\Token;
use XenSoluce\InviteSystem\Service\InvitationEmail;
use XF\Mvc\ParameterBag;
use XF\Repository\Payment;
use XF\Repository\PaymentRepository;

class Account extends XFCP_Account
{
    public function actionInvitation()
    {
        $visitor = \XF::visitor();
        $Ban = $this->finder('XenSoluce\InviteSystem:Banning')
            ->where('user_id', $visitor->user_id)
            ->fetchOne();
        if (!empty($Ban))
        {
            $viewParams = ['Ban' => $Ban];
            return $this->view('XenSoluce\InviteSystem:Invitation', 'xs_is_account_Invitation_ban', $viewParams);
        }

        if(!$visitor->canInvite())
        {
            return $this->noPermission();
        }

        if ($this->isPost())
        {
            $options = \XF::options();
            $InvitationPerXDay = $this->InvitationPerXDay($visitor);
            if(!$InvitationPerXDay['Between'])
            {
                throw $this->exception(
                    $this->error(\XF::phrase('xs_is_general_code_limit_reached', ['PerMonth' => $InvitationPerXDay['PerMonth'], 'xDay' => $InvitationPerXDay['xDay']]))
                );
            }
            $token = $this->filter('token', 'int');

            /** @var Token $tokenR */
            $tokenR = $this->em()->find('XenSoluce\InviteSystem:Token', $token);

            if(!$tokenR->canUse())
            {
                return $this->noPermission();
            }
            $sendEmil = $this->filter('send_invite', 'bool');
            $email = $this->filter('email', 'str');

            if(
                $visitor->hasPermission('xs_is', 'xs_is_only_with_email')
                && $options->xs_is_predefined_message_email
            ) {
                if(empty($email))
                {
                    throw $this->exception(
                        $this->error(\XF::phrase('please_enter_value_for_required_field_x', ['field' => 'email']))
                    );
                }
                $sendEmil = true;
            }

            if($sendEmil)
            {
                if(empty($email))
                {
                    throw $this->exception(
                        $this->error(\XF::phrase('please_enter_value_for_required_field_x', ['field' => 'email']))
                    );
                }
                /** @var InvitationEmail $invitationEmailService */
                $invitationEmailService = $this->service('XenSoluce\InviteSystem:InvitationEmail', false);
                $invitationEmailService->setEmail($email);
                $invitationEmailService->setVerifyEmail((bool)\XF::options()->xs_is_check_email_exist);

                if(!$invitationEmailService->validate($errors)) {
                    throw $this->exception($this->error($errors));
                }

                $invitationEmailService->setSubject(
                    \XF::phrase('xs_is_subject_of_the_email_by_default',
                        [
                            'username' => $visitor->username,
                            'boardTitle' => \XF::options()->boardTitle
                        ]
                    )
                );

                $invitationEmailService->setType(1);
                $invitationEmailService->setToken($tokenR->token, $token);
                $invitationEmailService->sendEmail();

                $Code = $invitationEmailService->getInvitation();
            }
            else
            {
                $Code = $this->em()->create('XenSoluce\InviteSystem:CodeInvitation');
                $Code->user_id = $visitor->user_id;
                $Code->token_id = $token;
                $Code->token = $tokenR->token;
                $Code->type_code = 1;
                $Code->save();
            }

            if($tokenR->enable_add_user_group)
            {
                $userGroupCode = $this->em()->create('XenSoluce\InviteSystem:UserGroupCode');
                $userGroupCode->code = $Code->code;
                $userGroupCode->entity_id = $Code->code_id;
                $userGroupCode->max_invite = 1;
                $userGroupCode->type_user_group = $tokenR->type_user_group;
                $userGroupCode->user_group = $tokenR->user_group;
                $userGroupCode->secondary_user_group = $tokenR->secondary_user_group;
                $userGroupCode->save();
            }
            return $this->redirect($this->buildLink('account/invitation', null, ['code' => $Code->code]));

        }
        else
        {
            $Invitations = $this->Invitation($visitor);
            $options = \XF::options();
            $page = $this->filterPage();
            $perPage = $options->xs_is_perPage_account;
            $Codes = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
                ->order('code_id', 'DESC')
                ->where([
                    'user_id'=> $visitor->user_id,
                    ['type_code', '!=', '2']
                ]);
            $Codes->limitByPage($page, $perPage);
            $viewParams = [
                'Invitations' => $Invitations,
                'code' => $this->filter('code', 'str'),
                'Codes' => $Codes->fetch(),
                'total' => $Codes->total(),
                'page' => $page,
                'perPage' => $perPage
            ];

            $view = $this->view('XenSoluce\InviteSystem:Invitation', 'xs_is_account_Invitation', $viewParams);
            return $this->addAccountWrapperParams($view, 'xs_Invitation');
        }
    }
    protected function Invitation(\XF\Entity\User $visitor)
    {

        $Tokens = $this->finder('XenSoluce\InviteSystem:Token')
            ->order('token_id', 'DESC');
        $Invitations['tokenGenerate'] = null;
        $Invitations['total'] = 0;
        $Invitations += $this->InvitationPerXDay($visitor);
        foreach ($Tokens->fetch() as $Token)
        {
            $isCount = 0;
            $isToken = false;
            $isNumber = false;
            $Codes = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
                ->where([
                    'user_id'=> $visitor->user_id,
                    'token_id' => $Token->token_id
                ]);
            if($Token->number_use == '1')
            {
                $isToken = empty($Codes->fetchOne());
            }
            elseif($Token->number_use > '1')
            {
                if(count($Codes->fetch()) < $Token->number_use)
                {
                    $isToken = true;
                }
                $isNumber = true;
            }
            if(in_array($visitor->user_id, $Token->user) && $isToken &&  $Token->type_token == '2')
            {
                $Invitations['tokenGenerate'][$Token->token_id] = [
                    'token' => $Token->token,
                    'tokenID' => $Token->token_id,
                    'tokenName' => $Token->title
                ];
                if(!$isNumber)
                {
                    $isCount = 1;
                }
                else
                {
                    $isCount = 2;
                }
            }
            if(in_array($visitor->user_group_id, $Token->user) && $isToken &&  $Token->type_token == '1')
            {
                $Invitations['tokenGenerate'][$Token->token_id] = [
                    'token' => $Token->token,
                    'tokenID' => $Token->token_id,
                    'tokenName' => $Token->title
                ];
                if(!$isNumber)
                {
                    $isCount = 1;
                }
                else
                {
                    $isCount = 2;
                }
            }
            foreach ($visitor->secondary_group_ids as $group)
            {
                if(in_array($group, $Token->user) && $isToken &&  $Token->type_token == '1')
                {
                    $Invitations['tokenGenerate'][$Token->token_id] = [
                        'token' => $Token->token,
                        'tokenID' => $Token->token_id,
                        'tokenName' => $Token->title
                    ];
                    if(!$isNumber)
                    {
                        $isCount = 1;
                    }
                    else
                    {
                        $isCount = 2;
                    }
                }
            }
            if($isCount === 1)
            {
                $Invitations['total']++;
            }
            elseif($isCount === 2)
            {
                $Invitations['total'] += $Token->number_use - count($Codes->fetch());
            }
        }

        return $Invitations;
    }

    /**
     * @throws \Exception
     */
    protected function InvitationPerXDay(\XF\Entity\User $visitor)
    {
        $XDay =  $visitor->hasPermission('xs_is', 'xs_is_x_d');
        $invitationsPerXDay = $visitor->hasPermission('xs_is', 'xs_is_can_invite_per_x_d');

        $timeZone = new \DateTimeZone('UTC');
        $Invitations['Between'] = true;
        if($invitationsPerXDay > 0)
        {
            $dateTime = new \DateTime('-' . $XDay . 'day 00:00', $timeZone);
            $dateTime->add(new \DateInterval('P1D'));
            $CodeInvitationCount = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
                ->where([
                    'user_id'=> $visitor->user_id,
                    ['type_code', '!=', ['2', '5', '6']]
                ])
                ->dateBetweenInvitation((int)$dateTime->format('U'), \XF::$time)
                ->total();

            if($invitationsPerXDay <= $CodeInvitationCount)
            {
                $Invitations = [
                    'Between' => false,
                    'PerMonth' => $invitationsPerXDay,
                    'xDay' => $XDay
                ];
            }
        }
        return $Invitations;
    }

    protected function canBuyInvitation()
    {
        if(!isset(\XF::options()->xs_is_code_buy['enable']) || !\XF::options()->xs_is_code_buy['enable']) {
            throw $this->exception($this->noPermission());
        }
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Reroute|\XF\Mvc\Reply\View
     */
    public function actionInvitationBuy(ParameterBag $params)
    {
        $this->canBuyInvitation();
        if($params->invitation_cart_id)
        {
            return $this->rerouteController(__CLASS__, 'InvitationBuyView', $params);
        }

        $paymentRepo = $this->repository(PaymentRepository::class);
        $profiles = $paymentRepo->findPaymentProfilesForList()
            ->pluckFrom(function ($e) {
                return ($e->display_title ?: $e->Provider->title);
            })
            ->where('payment_profile_id', \XF::options()->xs_is_code_buy['payment_profile_ids'])
            ->fetch();

        $options = \XF::options();
        $page = $params->page;
        $perPage = $options->xs_is_perPage_account;

        $invitationCarts = $this->finder('XenSoluce\InviteSystem:InvitationCarts')
            ->where([
                'user_id' => \XF::visitor()->user_id,
                ['state', '!=', 'no']
            ]);
        $invitationCarts->limitByPage($page, $perPage);

        $viewParams = [
            'profiles' => $profiles,
            'invitationCarts' => $invitationCarts->fetch(),
            'total' => $invitationCarts->total(),

            'page' => $page,
            'perPage' => $perPage
        ];
        $view = $this->view('XenSoluce\InviteSystem:InvitationBuy', 'xs_is_account_invitation_buy', $viewParams);
        return $this->addAccountWrapperParams($view, 'xs_invitation_buy');
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionInvitationBuyView(ParameterBag $params)
    {
        $this->canBuyInvitation();

        /** @var InvitationCarts $invitationCart */
        $invitationCart = $this->assertInvitationCartExists($params->invitation_cart_id);
        if($invitationCart->state === 'no')
        {
            throw $this->exception($this->notFound());
        }

        $viewParams = [
            'invitationCart' => $invitationCart,
        ];

        $view = $this->view('XenSoluce\InviteSystem:InvitationBuy', 'xs_is_account_invitation_buy_view', $viewParams);
        return $this->addAccountWrapperParams($view, 'xs_invitation_buy');
    }


    /**
     * @return mixed
     * @throws \XF\PrintableException
     */
    public function actionInvitationBuyComplete()
    {
        $this->canBuyInvitation();

        /** @var InvitationCarts $invitationCart */
        $invitationCart = \XF::em()->find('XenSoluce\InviteSystem:InvitationCarts', $this->filter('invitation_cart_id', 'int'));
        if(empty($invitationCart))
        {
            return $this->noPermission();
        }

        if($invitationCart->state === 'no') {
            $invitationCart->state = 'pending';
            $invitationCart->save();
        }

        $viewParams = [
            'invitationCart' => $invitationCart,
        ];
        $view = $this->view('XenSoluce\InviteSystem:InvitationBuy', 'xs_is_account_invitation_buy_complete', $viewParams);
        return $this->addAccountWrapperParams($view, 'xs_invitation_buy');
    }

    /**
     * @param $id
     * @param null $with
     * @param null $phraseKey
     * @return \XF\Mvc\Entity\Entity
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertInvitationCartExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('XenSoluce\InviteSystem:InvitationCarts', $id, $with, $phraseKey);
    }
}
