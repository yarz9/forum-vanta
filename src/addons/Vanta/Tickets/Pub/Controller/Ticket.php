<?php

namespace Vanta\Tickets\Pub\Controller;

use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Ticket extends AbstractController
{
    protected function canViewTickets(): bool
    {
        return \XF::visitor()->hasPermission('VantaTickets', 'vanta_tickets_view');
    }

    protected function canCreateTickets(): bool
    {
        return \XF::visitor()->hasPermission('VantaTickets', 'vanta_tickets_create');
    }

    protected function isStaff(): bool
    {
        return \XF::visitor()->hasPermission('VantaTickets', 'vanta_tickets_staff');
    }

    protected function getTicket(int $ticketId): ?array
    {
        return \XF::db()->fetchRow("
            SELECT *
            FROM xf_vanta_ticket
            WHERE ticket_id = ?
        ", $ticketId);
    }

    protected function canAccessTicket(array $ticket): bool
    {
        return $ticket['user_id'] == \XF::visitor()->user_id || $this->isStaff();
    }

    protected function escapeText($text): string
    {
        return nl2br(htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'));
    }

    protected function escapeLine($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }

    public function actionIndex()
    {
        if (!$this->canViewTickets())
        {
            return $this->noPermission();
        }

        $visitor = \XF::visitor();
        $db = \XF::db();
        $status = $this->filter('status', 'str');

        $where = [];
        $params = [];

        if (!$this->isStaff())
        {
            $where[] = 'user_id = ?';
            $params[] = $visitor->user_id;
        }
        else if ($status && in_array($status, ['open', 'pending', 'resolved', 'closed'], true))
        {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        else
        {
            $status = '';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $tickets = $db->fetchAll("
            SELECT *
            FROM xf_vanta_ticket
            {$whereSql}
            ORDER BY last_activity DESC
            LIMIT 100
        ", $params);

        foreach ($tickets AS &$ticket)
        {
            $ticket['title_html'] = $this->escapeLine($ticket['title']);
            $ticket['status_label'] = \XF::phrase('vanta_ticket_status_' . $ticket['status'])->render('raw');
            $ticket['type_label'] = \XF::phrase('vanta_ticket_type_' . $ticket['ticket_type'])->render('raw');
        }
        unset($ticket);

        return $this->view('Vanta\Tickets:Ticket', 'vanta_ticket_list', [
            'tickets' => $tickets,
            'status' => $status,
            'isStaff' => $this->isStaff(),
        ]);
    }

    public function actionCreate()
    {
        if (!$this->canViewTickets() || !$this->canCreateTickets())
        {
            return $this->noPermission();
        }

        $visitor = \XF::visitor();
        $db = \XF::db();

        if ($this->request->isPost())
        {
            $input = $this->filter([
                'ticket_type' => 'str',
                'title' => 'str',
                'message' => 'str',
            ]);

            $type = in_array($input['ticket_type'], ['general', 'hwid_reset', 'other'], true)
                ? $input['ticket_type']
                : 'general';

            $title = trim($input['title']);
            $message = trim($input['message']);

            if ($title === '' || $message === '')
            {
                return $this->error(\XF::phrase('vanta_ticket_title_or_message_required'));
            }

            $lastCreated = (int) $db->fetchOne("
                SELECT MAX(created)
                FROM xf_vanta_ticket
                WHERE user_id = ?
            ", $visitor->user_id);

            if ($lastCreated && (time() - $lastCreated) < 60)
            {
                return $this->error(\XF::phrase('vanta_ticket_flood'));
            }

            $hwid = null;
            $hwidHash = null;

            if ($type === 'hwid_reset')
            {
                $device = $db->fetchRow("
                    SELECT hwid, hwid_hash
                    FROM xf_vanta_device
                    WHERE user_id = ?
                    ORDER BY last_seen DESC
                    LIMIT 1
                ", $visitor->user_id);

                if (!$device)
                {
                    return $this->error(\XF::phrase('vanta_ticket_no_device'));
                }

                $hwid = $device['hwid'];
                $hwidHash = $device['hwid_hash'];
            }

            $now = time();

            $ticketId = $db->insert('xf_vanta_ticket', [
                'user_id' => $visitor->user_id,
                'username' => $visitor->username,
                'ticket_type' => $type,
                'title' => $title,
                'status' => 'open',
                'hwid' => $hwid,
                'hwid_hash' => $hwidHash,
                'message_count' => 1,
                'created' => $now,
                'last_activity' => $now,
            ]);

            $db->insert('xf_vanta_ticket_message', [
                'ticket_id' => $ticketId,
                'user_id' => $visitor->user_id,
                'username' => $visitor->username,
                'is_staff' => 0,
                'message' => $message,
                'created' => $now,
            ]);

            return $this->redirect($this->buildLink('tickets/view', null, ['ticket_id' => $ticketId]));
        }

        return $this->view('Vanta\Tickets:Ticket', 'vanta_ticket_create', [
            'ticketType' => 'general',
        ]);
    }

    public function actionView(ParameterBag $params)
    {
        if (!$this->canViewTickets())
        {
            return $this->noPermission();
        }

        $ticketId = $this->filter('ticket_id', 'uint');
        $ticket = $ticketId ? $this->getTicket($ticketId) : null;

        if (!$ticket || !$this->canAccessTicket($ticket))
        {
            return $this->error(\XF::phrase('vanta_ticket_not_found'));
        }

        $db = \XF::db();

        $messages = $db->fetchAll("
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

        return $this->view('Vanta\Tickets:Ticket', 'vanta_ticket_view', [
            'ticket' => $ticket,
            'messages' => $messages,
            'isStaff' => $this->isStaff(),
            'canReply' => $ticket['status'] !== 'closed',
        ]);
    }

    public function actionReply()
    {
        if (!$this->canViewTickets())
        {
            return $this->noPermission();
        }

        $this->assertPostOnly();

        $input = $this->filter([
            'ticket_id' => 'uint',
            'message' => 'str',
        ]);

        $ticket = $input['ticket_id'] ? $this->getTicket($input['ticket_id']) : null;

        if (!$ticket || !$this->canAccessTicket($ticket))
        {
            return $this->error(\XF::phrase('vanta_ticket_not_found'));
        }

        if ($ticket['status'] === 'closed')
        {
            return $this->error(\XF::phrase('vanta_ticket_closed'));
        }

        $message = trim($input['message']);
        if ($message === '')
        {
            return $this->error(\XF::phrase('vanta_ticket_message_required'));
        }

        $visitor = \XF::visitor();
        $db = \XF::db();
        $isStaff = $this->isStaff();
        $now = time();

        $db->insert('xf_vanta_ticket_message', [
            'ticket_id' => $ticket['ticket_id'],
            'user_id' => $visitor->user_id,
            'username' => $visitor->username,
            'is_staff' => $isStaff ? 1 : 0,
            'message' => $message,
            'created' => $now,
        ]);

        $newStatus = $ticket['status'];

        if ($isStaff)
        {
            if ($ticket['status'] === 'open')
            {
                $newStatus = 'pending';
            }
        }
        else
        {
            if (in_array($ticket['status'], ['pending', 'resolved'], true))
            {
                $newStatus = 'open';
            }
        }

        $db->update('xf_vanta_ticket', [
            'status' => $newStatus,
            'message_count' => $ticket['message_count'] + 1,
            'last_activity' => $now,
        ], 'ticket_id = ?', $ticket['ticket_id']);

        return $this->redirect($this->buildLink('tickets/view', null, ['ticket_id' => $ticket['ticket_id']]));
    }

    public function actionClose()
    {
        if (!$this->canViewTickets())
        {
            return $this->noPermission();
        }

        $this->assertPostOnly();

        $ticketId = $this->filter('ticket_id', 'uint');
        $ticket = $ticketId ? $this->getTicket($ticketId) : null;

        if (!$ticket || !$this->canAccessTicket($ticket))
        {
            return $this->error(\XF::phrase('vanta_ticket_not_found'));
        }

        \XF::db()->update('xf_vanta_ticket', [
            'status' => 'closed',
            'last_activity' => time(),
        ], 'ticket_id = ?', $ticket['ticket_id']);

        return $this->redirect($this->buildLink('tickets/view', null, ['ticket_id' => $ticket['ticket_id']]));
    }
}
