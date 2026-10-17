<?php

namespace XenSoluce\InviteSystem\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Reroute;
use XF\Mvc\Reply\View;

class Logs extends AbstractController
{
    public function actionOrder(ParameterBag $params)
    {
        if($params->invitation_cart_id) {
            return $this->rerouteController(__CLASS__, 'OrderView', $params);
        }

        $options = \XF::options();
        $page = $params->page;
        $perPage = $options->xs_is_perPage_account;

        $carts = $this->finder('XenSoluce\InviteSystem:InvitationCarts')
            ->order('cart_date', 'DESC');

        $carts->limitByPage($page, $perPage);

        $viewParams = [
            'carts' => $carts->fetch(),
            'total' => $carts->total(),

            'page' => $page,
            'perPage' => $perPage
        ];

        return $this->view(
            'XenSoluce\InviteSystem:Invitation\Orders',
            'xs_is_invitation_order',
            $viewParams
        );
    }

    public function actionOrderView(ParameterBag $params): View
    {
        $order = $this->assertInvitationCartExists($params->invitation_cart_id);

        $viewParams = [
            'order' => $order,
        ];

        return $this->view(
            'XenSoluce\InviteSystem:Invitation\Orders\View',
            'xs_is_invitation_order_view',
            $viewParams
        );
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