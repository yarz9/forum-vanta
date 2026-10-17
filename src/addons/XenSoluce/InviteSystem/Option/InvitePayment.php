<?php

namespace XenSoluce\InviteSystem\Option;

use XF\Option\AbstractOption;
use \XF\Entity\Option;

class InvitePayment extends AbstractOption
{
    /**
     * @param Option $option
     * @param array $htmlParams
     * @return string
     */
    public static function renderOptionInvitePayment(Option $option, array $htmlParams)
	{
        $paymentRepo = \XF::repository('XF:Payment');
        $paymentProfiles = $paymentRepo->findPaymentProfilesForList()->fetch();
		return self::getTemplate('admin:option_template_xs_is_code_buy', $option, $htmlParams, [
            'profiles' => $paymentProfiles,
		]);
    }

    /**
     * @param Option $option
     * @param array $htmlParams
     * @return string
     */
    public static function renderOptionInvitePaymentRegister(Option $option, array $htmlParams)
    {
        $paymentRepo = \XF::repository('XF:Payment');
        $paymentProfiles = $paymentRepo->findPaymentProfilesForList()->fetch();
        return self::getTemplate('admin:option_template_xs_is_code_buy_register', $option, $htmlParams, [
            'profiles' => $paymentProfiles,
        ]);
    }
}