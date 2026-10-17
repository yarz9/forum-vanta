<?php

namespace XenSoluce\InviteSystem\XF\Pub\Controller;

use XenSoluce\InviteSystem\Entity\CodeInvitation;
use XenSoluce\InviteSystem\Entity\InvitationBuy;
use XenSoluce\InviteSystem\Entity\RegisterUser;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\ConnectedAccount\ProviderData\AbstractProviderData;
use XF\Mvc\Reply\View;
use XF\PrintableException;

class Register extends XFCP_Register
{
    public function actionIndex()
    {
        $parent = parent::actionIndex();

        if($parent instanceof View) {
            $code = $this->filter('invitation_code', 'str');
            /** @var CodeInvitation $codeFinder */
            $codeFinder = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
                ->where('code', $code)
                ->fetchOne();
            $personalizedCodeFinder = $this->finder('XenSoluce\InviteSystem:PersonalizedInvitationCode')
                ->where([
                    'code' => $code,
                    'enable' => 1
                ])
                ->fetchOne();

            /** @var InvitationBuy $buyCodeFinder */
            $buyCodeFinder = $this->finder('XenSoluce\InviteSystem:InvitationBuy')
                ->where('code', $code)
                ->where('InvitationCart.state', 'validate')
                ->fetchOne();

            if($buyCodeFinder || $personalizedCodeFinder || $codeFinder) {
                $parent->setParam('invitationCode', $code);
            }

            $option = \XF::options()->xs_is_code_buy_register;

            if(!$option['enable']
                || empty($option['payment_profile_ids'])
                || $option['price'] <= 0
                || \XF::visitor()->user_id
                || \XF::options()->xs_is_code_required['mandatory'] !== 'yes'
            ) {
                $parent->setParam('canBuyCode', false);
            } else {
                $parent->setParam('canBuyCode', true);
            }

        }

        return $parent;
    }

    public function actionRegister()
    {
        $code = $this->filter('code','str');
        $error = $this->getErrorInvitation($code);
        if($error)
        {
            return $error;
        }
        return parent::actionRegister();
    }
    public function actionConnectedAccountRegister(ParameterBag $params)
    {
        $code = $this->filter('code','str');
        $error = $this->getErrorInvitation($code);
        if($error)
        {
            return $error;
        }
        return parent::actionConnectedAccountRegister($params);
    }
    protected function getConnectedRegistrationInput(AbstractProviderData $providerData)
    {
        $input = parent::getConnectedRegistrationInput($providerData);
        $input += $this->filter(['code' => 'str']);
        return $input;
    }

    protected function getRegistrationInput(\XF\Service\User\RegisterForm $regForm)
    {
        $input = parent::getRegistrationInput($regForm);
        $input += $this->filter(['code' => 'str']);
        return $input;
    }

    /**
     * @throws PrintableException
     */
    protected function finalizeRegistration(\XF\Entity\User $user)
    {
        parent::finalizeRegistration($user);

        $code = $this->filter('code','str');
        $PersonalizedCodeFinder = $this->finder('XenSoluce\InviteSystem:PersonalizedInvitationCode')->where('code', $code)->fetchOne();
        $codeFinder = $this->finder('XenSoluce\InviteSystem:CodeInvitation')->where('code', $code)->fetchOne();
        /** @var InvitationBuy $buyCode */
        $buyCode = $this->finder('XenSoluce\InviteSystem:InvitationBuy')
            ->where('code', $code)
            ->where('InvitationCart.state', 'validate')
            ->fetchOne();

        if(!empty($buyCode) && $buyCode->InvitationCart->buy_type === 'register') {
            $buyCode->InvitationCart->user_id = $user->user_id;
            $buyCode->InvitationCart->save();
        }

        $options = \XF::options();
        $codeTest = false;
        if(!empty($PersonalizedCodeFinder) || !empty($codeFinder) || !empty($buyCode))
        {
            $codeTest = true;
        }
        if($codeTest && $options->xs_is_code_required['mandatory'] == 'yes')
        {
            if(!empty($PersonalizedCodeFinder))
            {
                if(count($PersonalizedCodeFinder->registered_user_id) == 0)
                {
                    $registeredUser = [$user->user_id];
                }
                else
                {
                    $registeredUser = implode(',', $PersonalizedCodeFinder->registered_user_id);
                    $registeredUser = [$registeredUser, $user->user_id];
                }
                $PersonalizedCodeFinder->registered_user_id =  $registeredUser;
                $PersonalizedCodeFinder->save();
            }
        }
        elseif ($code && $options->xs_is_code_required['mandatory'] == 'no')
        {
            if(!empty($PersonalizedCodeFinder))
            {
                $registeredUser = implode(',', $PersonalizedCodeFinder->registered_user_id);
                $registeredUser = [$registeredUser, $user->user_id];
                $PersonalizedCodeFinder->registered_user_id =  $registeredUser;
                $PersonalizedCodeFinder->save();
            }
        }

        if(!empty($codeFinder))
        {
            $this->setValueForCode($codeFinder, $codeFinder->user_id, $user, $codeFinder->invitation_date);
        }

        if(!empty($buyCode))
        {
            if($buyCode->InvitationCart->buy_type === 'login') {
                $this->setValueForCode(
                    $buyCode,
                    $buyCode->InvitationCart->user_id,
                    $user,
                    $buyCode->InvitationCart->cart_date
                );
            } else {
                $this->createRegisterUser(
                    $buyCode,
                    $buyCode->InvitationCart->user_id,
                    $user,
                    $buyCode->InvitationCart->cart_date
                );
            }
        }
    }

    /**
     * @param Entity $entityCode
     * @param int $userIdAlert
     * @param User $user
     * @param int $date
     * @return void
     * @throws PrintableException
     */
    protected function setValueForCode(Entity $entityCode, int $userIdAlert, User $user, int $date): void
    {
        $alertRepo = $this->repository('XF:UserAlert');

        $userInvitation = $this->finder('XF:User')->where('user_id', $userIdAlert)->fetchOne();
        $extra = [
            'link' => \XF::app()->router('public')->buildLink('members', $user),
            'user' => $user->username
        ];
        $alertRepo->alert($userInvitation, $user->user_id, '', 'user', $user->user_id, 'xs_is_invitations_alert', $extra);

        $userInvitation->xs_is_invite_count += 1;
        $entityCode->registered_user_id = $user->user_id;
        $entityCode->save();
        $userInvitation->save();

        $this->createRegisterUser($entityCode, $userIdAlert, $user, $date);
    }

    protected function createRegisterUser(Entity $entityCode, int $userIdAlert, User $user, $date)
    {
        /** @var RegisterUser $registerUser */
        $registerUser = $this->em()->create('XenSoluce\InviteSystem:RegisterUser');
        $registerUser->by_user_id = $userIdAlert;
        $registerUser->registered_user_id = $user->user_id;
        $registerUser->invitation_date = $date;
        $registerUser->code_id = $entityCode->getEntityId();
        $registerUser->table_name = $entityCode->structure()->shortName;
        $registerUser->save();

        return $registerUser;
    }

    /**
     * @param $code
     * @return \XF\Mvc\Reply\Error
     */
    protected function getErrorInvitation($code)
    {
        /** @var CodeInvitation $codeFinder */
        $codeFinder = $this->finder('XenSoluce\InviteSystem:CodeInvitation')
            ->where('code', $code)
            ->fetchOne();
        $PersonalizedCodeFinder = $this->finder('XenSoluce\InviteSystem:PersonalizedInvitationCode')
            ->where([
                'code' => $code,
                'enable' => 1
            ])
            ->fetchOne();

        /** @var InvitationBuy $buyCode */
        $buyCode = $this->finder('XenSoluce\InviteSystem:InvitationBuy')
            ->where('code', $code)
            ->where('InvitationCart.state', 'validate')
            ->fetchOne();

        $options = \XF::options();

        if(!$code && $options->xs_is_code_required['mandatory'] == 'yes')
        {
            return $this->error(\XF::phrase('please_enter_value_for_required_field_x', ['field' => \XF::phrase('xs_is_code')]));
        }
        if($options->xs_is_code_required['mandatory'] == 'yes')
        {
            return $this->getMessageError($codeFinder, $PersonalizedCodeFinder, $buyCode, $code);
        }
        elseif ($code && $options->xs_is_code_required['mandatory'] == 'no')
        {
            return $this->getMessageError($codeFinder, $PersonalizedCodeFinder, $buyCode, $code);
        }

        return null;
    }

    public function getMessageError($codeFinder, $personalizedCodeFinder, ?InvitationBuy $buyCode, $code)
    {
        if(empty($codeFinder) && empty($personalizedCodeFinder) && empty($buyCode))
        {
            return $this->error(\XF::phrase('xs_is_invalid_code', ['code' => $code]));
        }
        if(!empty($buyCode))
        {
            if($buyCode->registered_user_id)
            {
                return $this->error(\XF::phrase('xs_is_code_already_used', ['code' => $code]));
            }
        }
        if(!empty($codeFinder))
        {
            if($codeFinder->registered_user_id)
            {
                return $this->error(\XF::phrase('xs_is_code_already_used', ['code' => $code]));
            }
        }
        if(!empty($personalizedCodeFinder))
        {
            if($personalizedCodeFinder->limit_use >= 1)
            {
                if(count($personalizedCodeFinder->registered_user_id) >= $personalizedCodeFinder->limit_use)
                {
                    if($personalizedCodeFinder->limit_use == 1)
                    {
                        return $this->error(\XF::phrase('xs_is_code_already_used', ['code' => $code]));
                    }
                    else
                    {
                        return $this->error(\XF::phrase('xs_is_code_reached_maximum_number_registrations', [
                            'code' => $code,
                            'number' => $personalizedCodeFinder->limit_use
                        ]));
                    }
                }
            }
            if($personalizedCodeFinder->limit_time >= 1)
            {
                if($personalizedCodeFinder->limit_time <= \XF::$time)
                {
                    return $this->error(\XF::phrase('xs_is_no_longer_valid', ['code' => $code]));
                }
            }
        }
        return null;
    }
}
