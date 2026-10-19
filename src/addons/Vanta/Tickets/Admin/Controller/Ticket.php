<?php

namespace Vanta\Tickets\Admin\Controller;

use XF\Admin\Controller\AbstractController;

class Ticket extends AbstractController
{
    protected function escapeText($text): string
    {
        return nl2br(htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'));
    }

    protected function escapeLine($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }

    protected function getTicket(int $ticketId): ?array
    {
        return \XF::db()->fetchRow("
            SELECT t.*, u.username AS user_username
            FROM xf_vanta_ticket t
            LEFT JOIN xf_user u ON u.user_id = t.user_id
            WHERE t.ticket_id = ?
        ", $ticketId);
    }

    public function actionIndex()
    {
        $this->assertAdminPermission('vantaTicketsManage');

        $db = \XF::db();
        $page = $this->filterPage();
        $perPage = 50;

        $statuses = ['open', 'pending', 'resolved', 'closed'];
        $statusFilter = $this->filter('status', 'str');
        if (!in_array($statusFilter, $statuses, true))
        {
            $statusFilter = '';
        }

        $counts = [];
        $pairs = $db->fetchPairs("SELECT status, COUNT(*) FROM xf_vanta_ticket GROUP BY status");
        foreach ($statuses AS $status)
        {
            $counts[$status] = (int) ($pairs[$status] ?? 0);
        }
        $counts['all'] = array_sum($counts);

        $total = $statusFilter ? $counts[$statusFilter] : $counts['all'];
        $params = [];

        if ($statusFilter)
        {
            $whereSql = 'WHERE t.status = ?';
            $params[] = $statusFilter;
        }
        else
        {
            $whereSql = '';
        }

        $tickets = $db->fetchAll($db->limit("
            SELECT t.*, u.username AS user_username
            FROM xf_vanta_ticket t
            LEFT JOIN xf_user u ON u.user_id = t.user_id
            {$whereSql}
            ORDER BY t.last_activity DESC
        ", $perPage, ($page - 1) * $perPage), $params);

        foreach ($tickets AS &$ticket)
        {
            $ticket['title_html'] = $this->escapeLine($ticket['title']);
            $ticket['status_label'] = \XF::phrase('vanta_ticket_status_' . $ticket['status'])->render('raw');
            $ticket['type_label'] = \XF::phrase('vanta_ticket_type_' . $ticket['ticket_type'])->render('raw');
        }
        unset($ticket);

        return $this->view('Vanta\Tickets:Ticket', 'vanta_ticket_admin_list', [
            'tickets' => $tickets,
            'statusFilter' => $statusFilter,
            'counts' => $counts,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
        ]);
    }

    public function actionView()
    {
        $this->assertAdminPermission('vantaTicketsManage');

        $ticketId = $this->filter('ticket_id', 'uint');
        $ticket = $ticketId ? $this->getTicket($ticketId) : null;

        if (!$ticket)
        {
            return $this->error(\XF::phrase('vanta_ticket_not_found'));
        }

        $messages = \XF::db()->fetchAll("
            SELECT *
            FROM xf_vanta_ticket_message
            WHERE ticket_id = ?
            ORDER BY created ASC
        ", $ticket['ticket_id']);

        foreach ($messages AS &$message)
        {
            $message['message_html'] = $this->escapeText($message['message']);
        }
        unset($message);

        $ticket['title_html'] = $this->escapeLine($ticket['title']);
        $ticket['hwid_html'] = $ticket['hwid'] ? $this->escapeLine($ticket['hwid']) : '';
        $ticket['status_label'] = \XF::phrase('vanta_ticket_status_' . $ticket['status'])->render('raw');
        $ticket['type_label'] = \XF::phrase('vanta_ticket_type_' . $ticket['ticket_type'])->render('raw');

        return $this->view('Vanta\Tickets:Ticket', 'vanta_ticket_admin_view', [
            'ticket' => $ticket,
            'messages' => $messages,
        ]);
    }

    public function actionReply()
    {
        $this->assertAdminPermission('vantaTicketsManage');

        if (!$this->request->isPost())
        {
            return $this->redirect($this->buildLink('vanta-tickets'));
        }

        $input = $this->filter([
            'ticket_id' => 'uint',
            'message' => 'str',
        ]);

        $ticket = $input['ticket_id'] ? $this->getTicket($input['ticket_id']) : null;

        if (!$ticket)
        {
            return $this->error(\XF::phrase('vanta_ticket_not_found'));
        }

        $message = trim($input['message']);
        if ($message === '')
        {
            return $this->error(\XF::phrase('vanta_ticket_message_required'));
        }

        $visitor = \XF::visitor();
        $db = \XF::db();
        $now = time();

        $db->insert('xf_vanta_ticket_message', [
            'ticket_id' => $ticket['ticket_id'],
            'user_id' => $visitor->user_id,
            'username' => $visitor->username,
            'is_staff' => 1,
            'message' => $message,
            'created' => $now,
        ]);

        $newStatus = $ticket['status'] === 'open' ? 'pending' : $ticket['status'];

        $db->update('xf_vanta_ticket', [
            'status' => $newStatus,
            'message_count' => $ticket['message_count'] + 1,
            'last_activity' => $now,
        ], 'ticket_id = ?', $ticket['ticket_id']);

        return $this->redirect($this->buildLink('vanta-tickets/view', null, ['ticket_id' => $ticket['ticket_id']]));
    }

    public function actionStatus()
    {
        $this->assertAdminPermission('vantaTicketsManage');

        if (!$this->request->isPost())
        {
            return $this->redirect($this->buildLink('vanta-tickets'));
        }

        $input = $this->filter([
            'ticket_id' => 'uint',
            'status' => 'str',
        ]);

        if (!in_array($input['status'], ['open', 'pending', 'resolved', 'closed'], true))
        {
            return $this->error(\XF::phrase('vanta_ticket_invalid_status'));
        }

        $ticket = $input['ticket_id'] ? $this->getTicket($input['ticket_id']) : null;

        if (!$ticket)
        {
            return $this->error(\XF::phrase('vanta_ticket_not_found'));
        }

        \XF::db()->update('xf_vanta_ticket', [
            'status' => $input['status'],
            'last_activity' => time(),
        ], 'ticket_id = ?', $ticket['ticket_id']);

        return $this->redirect($this->buildLink('vanta-tickets/view', null, ['ticket_id' => $ticket['ticket_id']]));
    }

    public function actionResethwid()
    {
        $this->assertAdminPermission('vantaTicketsManage');

        if (!$this->request->isPost())
        {
            return $this->redirect($this->buildLink('vanta-tickets'));
        }

        $ticketId = $this->filter('ticket_id', 'uint');
        $ticket = $ticketId ? $this->getTicket($ticketId) : null;

        if (!$ticket)
        {
            return $this->error(\XF::phrase('vanta_ticket_not_found'));
        }

        if ($ticket['ticket_type'] !== 'hwid_reset')
        {
            return $this->error(\XF::phrase('vanta_ticket_not_hwid_request'));
        }

        $visitor = \XF::visitor();
        $db = \XF::db();
        $now = time();

        $hwidHash = $ticket['hwid_hash'];

        if ($hwidHash)
        {
            $db->delete('xf_vanta_device', 'user_id = ? AND hwid_hash = ?', [$ticket['user_id'], $hwidHash]);
        }
        else
        {
            $db->delete('xf_vanta_device', 'user_id = ?', $ticket['user_id']);
        }

        $db->insert('xf_vanta_reset', [
            'user_id' => $ticket['user_id'],
            'hwid_hash' => $hwidHash ?: hash('sha256', 'vanta_ticket_all'),
            'reason' => 'Ticket #' . $ticket['ticket_id'] . ' HWID reset',
            'requested_by' => $ticket['user_id'],
            'requested_by_username' => $ticket['username'],
            'approved_by' => $visitor->user_id,
            'approved_by_username' => $visitor->username,
            'created' => $now,
            'processed' => $now,
            'status' => 'approved',
        ]);

        $db->insert('xf_vanta_log', [
            'user_id' => $ticket['user_id'],
            'hwid_hash' => $hwidHash,
            'ip_address' => null,
            'event_type' => 'reset_request',
            'detail' => 'Ticket #' . $ticket['ticket_id'],
            'timestamp' => $now,
        ]);

        $db->insert('xf_vanta_ticket_message', [
            'ticket_id' => $ticket['ticket_id'],
            'user_id' => $visitor->user_id,
            'username' => $visitor->username,
            'is_staff' => 1,
            'message' => \XF::phrase('vanta_ticket_hwid_reset_done', [
                'staff' => $visitor->username,
            ])->render('raw'),
            'created' => $now,
        ]);

        $db->update('xf_vanta_ticket', [
            'status' => 'resolved',
            'message_count' => $ticket['message_count'] + 1,
            'last_activity' => $now,
        ], 'ticket_id = ?', $ticket['ticket_id']);

        return $this->redirect($this->buildLink('vanta-tickets/view', null, ['ticket_id' => $ticket['ticket_id']]));
    }
}
