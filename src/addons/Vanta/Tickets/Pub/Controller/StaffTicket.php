<?php

namespace Vanta\Tickets\Pub\Controller;

use XF\Mvc\ParameterBag;

class StaffTicket extends Ticket
{
    protected function preDispatchType($action, ParameterBag $params)
    {
        if (!$this->isStaff())
        {
            throw $this->exception($this->noPermission());
        }
    }

    public function actionIndex()
    {
        $db = \XF::db();

        $status = $this->filter('status', 'str');
        if (!in_array($status, self::STATUSES, true))
        {
            $status = '';
        }

        $whereSql = '';
        $params = [];

        if ($status !== '')
        {
            $whereSql = 'WHERE status = ?';
            $params[] = $status;
        }

        $tickets = $db->fetchAll("
            SELECT *
            FROM xf_vanta_ticket
            {$whereSql}
            ORDER BY last_activity DESC
            LIMIT 200
        ", $params);

        foreach ($tickets AS &$ticket)
        {
            $ticket['title_html'] = $this->escapeLine($ticket['title']);
            $ticket['status_label'] = $this->statusLabel($ticket['status']);
            $ticket['type_label'] = $this->typeLabel($ticket['ticket_type']);
        }
        unset($ticket);

        return $this->view('Vanta\Tickets:Ticket', 'vanta_ticket_list', [
            'tickets' => $tickets,
            'status' => $status,
            'isStaff' => true,
            'staffMode' => true,
            'statuses' => $this->statusOptions(),
        ]);
    }
}
