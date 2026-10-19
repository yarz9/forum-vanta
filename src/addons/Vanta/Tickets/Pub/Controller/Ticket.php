<?php

namespace Vanta\Tickets\Pub\Controller;

use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Ticket extends AbstractController
{
    protected const STATUSES = ['open', 'pending', 'investigating', 'locked', 'resolved', 'closed'];

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

    protected function statusLabel(string $status): string
    {
        return \XF::phrase('vanta_ticket_status_' . $status)->render('raw');
    }

    protected function typeLabel(string $type): string
    {
        return \XF::phrase('vanta_ticket_type_' . $type)->render('raw');
    }

    protected function statusOptions(): array
    {
        $options = [];
        foreach (self::STATUSES AS $status)
        {
            $options[$status] = $this->statusLabel($status);
        }

        return $options;
    }

    protected function getStaffMembers(): array
    {
        return \XF::db()->fetchAll("
            SELECT u.user_id, u.username
            FROM xf_user u
            INNER JOIN xf_user_group_relation r ON r.user_id = u.user_id
            WHERE r.user_group_id IN (3, 4)
              AND u.is_banned = 0
            GROUP BY u.user_id, u.username
            ORDER BY u.username
        ");
    }

    protected function isStaffUser(int $userId): ?array
    {
        if (!$userId)
        {
            return null;
        }

        return \XF::db()->fetchRow("
            SELECT u.user_id, u.username
            FROM xf_user u
            INNER JOIN xf_user_group_relation r ON r.user_id = u.user_id
            WHERE u.user_id = ?
              AND r.user_group_id IN (3, 4)
              AND u.is_banned = 0
            LIMIT 1
        ", $userId) ?: null;
    }

    protected function getDeviceInfo(array $ticket): array
    {
        $device = \XF::db()->fetchRow("
            SELECT *
            FROM xf_vanta_device
            WHERE user_id = ?
            ORDER BY last_seen DESC
            LIMIT 1
        ", $ticket['user_id']);

        $specs = [];
        if ($device && !empty($device['specs']))
        {
            $decoded = json_decode($device['specs'], true);
            if (is_array($decoded))
            {
                $specs = $decoded;
            }
        }

        if ($device)
        {
            $device['create_ip_text'] = $this->ipText($device['create_ip'] ?? '');
        }

        foreach ($specs AS $key => $value)
        {
            if (is_string($value))
            {
                $specs[$key] = $this->escapeLine($value);
            }
        }

        return [$device ?: null, $specs];
    }

    protected function ipText($binary): string
    {
        if (!$binary || !is_string($binary))
        {
            return '';
        }

        $ip = @inet_ntop($binary);

        return $ip !== false ? $ip : '';
    }

    protected function getRecentSessions(int $userId, int $limit = 8): array
    {
        $rows = \XF::db()->fetchAll("
            SELECT session_token, login_ip, login_user_agent, created, last_activity
            FROM xf_vanta_session
            WHERE user_id = ?
            ORDER BY created DESC
            LIMIT " . (int) $limit, [$userId]);

        foreach ($rows AS &$row)
        {
            unset($row['session_token']);
            $row['ip_text'] = $this->ipText($row['login_ip']);
            $row['user_agent_html'] = $this->escapeLine($row['login_user_agent'] ?? '');
        }
        unset($row);

        return $rows;
    }

    protected function getRecentLogs(int $userId, int $limit = 12): array
    {
        return \XF::db()->fetchAll("
            SELECT event_type, detail, timestamp
            FROM xf_vanta_log
            WHERE user_id = ?
            ORDER BY timestamp DESC
            LIMIT " . (int) $limit, [$userId]);
    }

    protected function getParticipants(array $messages, int $ticketOwnerId, int $assignedUserId): array
    {
        $ids = [$ticketOwnerId => true];
        if ($assignedUserId)
        {
            $ids[$assignedUserId] = true;
        }

        $counts = [];
        $staffIds = [];

        foreach ($messages AS $message)
        {
            $userId = (int) $message['user_id'];
            if (!$userId)
            {
                continue;
            }

            $ids[$userId] = true;
            $counts[$userId] = ($counts[$userId] ?? 0) + 1;

            if (!empty($message['is_staff']))
            {
                $staffIds[$userId] = true;
            }
        }

        $users = \XF::finder('XF:User')->whereIds(array_keys($ids))->fetch();

        $participants = [];
        foreach ($users AS $userId => $user)
        {
            $participants[] = [
                'user' => $user,
                'message_count' => $counts[$userId] ?? 0,
                'is_staff' => isset($staffIds[$userId]),
                'is_owner' => $userId === $ticketOwnerId,
                'is_assigned' => $userId === $assignedUserId,
            ];
        }

        usort($participants, function (array $a, array $b)
        {
            return $b['message_count'] <=> $a['message_count'];
        });

        return $participants;
    }

    public function actionIndex()
    {
        if (!$this->canViewTickets())
        {
            return $this->noPermission();
        }

        $visitor = \XF::visitor();
        $db = \XF::db();

        $tickets = $db->fetchAll("
            SELECT *
            FROM xf_vanta_ticket
            WHERE user_id = ?
            ORDER BY last_activity DESC
            LIMIT 100
        ", [$visitor->user_id]);

        foreach ($tickets AS &$ticket)
        {
            $ticket['title_html'] = $this->escapeLine($ticket['title']);
            $ticket['status_label'] = $this->statusLabel($ticket['status']);
            $ticket['type_label'] = $this->typeLabel($ticket['ticket_type']);
        }
        unset($ticket);

        return $this->view('Vanta\Tickets:Ticket', 'vanta_ticket_list', [
            'tickets' => $tickets,
            'status' => '',
            'isStaff' => $this->isStaff(),
            'staffMode' => false,
            'statuses' => $this->statusOptions(),
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

            $db->insert('xf_vanta_ticket', [
                'user_id' => $visitor->user_id,
                'username' => $visitor->username,
                'ticket_type' => $type,
                'title' => $title,
                'status' => 'open',
                'assigned_user_id' => 0,
                'assigned_username' => '',
                'hwid' => $hwid,
                'hwid_hash' => $hwidHash,
                'message_count' => 1,
                'created' => $now,
                'last_activity' => $now,
            ]);

            // insert() returns affected rows, not the new ID.
            $ticketId = (int) $db->lastInsertId();

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
        $isStaff = $this->isStaff();

        $staffMode = strpos((string) $this->request->getRoutePath(), 'staff-tickets') === 0;

        $messages = $db->fetchAll("
            SELECT *
            FROM xf_vanta_ticket_message
            WHERE ticket_id = ?
            ORDER BY created ASC
        ", [$ticket['ticket_id']]);

        $userIds = [];
        foreach ($messages AS $message)
        {
            if ($message['user_id'])
            {
                $userIds[(int) $message['user_id']] = true;
            }
        }

        $users = $userIds ? \XF::finder('XF:User')->whereIds(array_keys($userIds))->fetch() : [];

        foreach ($messages AS &$message)
        {
            $message['message_html'] = $this->escapeText($message['message']);
            $message['user'] = $users[(int) $message['user_id']] ?? null;
        }
        unset($message);

        $ticket['title_html'] = $this->escapeLine($ticket['title']);
        $ticket['hwid_html'] = $ticket['hwid'] ? $this->escapeLine($ticket['hwid']) : '';
        $ticket['status_label'] = $this->statusLabel($ticket['status']);
        $ticket['type_label'] = $this->typeLabel($ticket['ticket_type']);

        [$device, $specs] = $this->getDeviceInfo($ticket);

        $participants = $this->getParticipants(
            $messages,
            (int) $ticket['user_id'],
            (int) ($ticket['assigned_user_id'] ?? 0)
        );

        $canReply = $ticket['status'] !== 'closed'
            && ($isStaff || $ticket['status'] !== 'locked');

        return $this->view('Vanta\Tickets:Ticket', 'vanta_ticket_view', [
            'ticket' => $ticket,
            'messages' => $messages,
            'isStaff' => $isStaff,
            'canReply' => $canReply,
            'participants' => $participants,
            'staffMembers' => $isStaff ? $this->getStaffMembers() : [],
            'device' => $device,
            'specs' => $specs,
            'sessions' => $this->getRecentSessions((int) $ticket['user_id']),
            'logs' => $this->getRecentLogs((int) $ticket['user_id']),
            'statuses' => $this->statusOptions(),
            'staffMode' => $staffMode,
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

        $isStaff = $this->isStaff();

        if ($ticket['status'] === 'closed')
        {
            return $this->error(\XF::phrase('vanta_ticket_closed'));
        }

        if ($ticket['status'] === 'locked' && !$isStaff)
        {
            return $this->error(\XF::phrase('vanta_ticket_locked'));
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
            if (in_array($ticket['status'], ['pending', 'investigating', 'resolved'], true))
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

    public function actionStatus()
    {
        if (!$this->canViewTickets() || !$this->isStaff())
        {
            return $this->noPermission();
        }

        $this->assertPostOnly();

        $input = $this->filter([
            'ticket_id' => 'uint',
            'status' => 'str',
        ]);

        if (!in_array($input['status'], self::STATUSES, true))
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

        return $this->redirect($this->buildLink('tickets/view', null, ['ticket_id' => $ticket['ticket_id']]));
    }

    public function actionAssign()
    {
        if (!$this->canViewTickets() || !$this->isStaff())
        {
            return $this->noPermission();
        }

        $this->assertPostOnly();

        $input = $this->filter([
            'ticket_id' => 'uint',
            'assigned_user_id' => 'uint',
        ]);

        $ticket = $input['ticket_id'] ? $this->getTicket($input['ticket_id']) : null;

        if (!$ticket)
        {
            return $this->error(\XF::phrase('vanta_ticket_not_found'));
        }

        $assignedUserId = 0;
        $assignedUsername = '';

        if ($input['assigned_user_id'])
        {
            $staffUser = $this->isStaffUser($input['assigned_user_id']);
            if (!$staffUser)
            {
                return $this->error(\XF::phrase('vanta_ticket_assign_invalid_staff'));
            }

            $assignedUserId = (int) $staffUser['user_id'];
            $assignedUsername = $staffUser['username'];
        }

        \XF::db()->update('xf_vanta_ticket', [
            'assigned_user_id' => $assignedUserId,
            'assigned_username' => $assignedUsername,
            'last_activity' => time(),
        ], 'ticket_id = ?', $ticket['ticket_id']);

        return $this->redirect($this->buildLink('tickets/view', null, ['ticket_id' => $ticket['ticket_id']]));
    }

    public function actionResethwid()
    {
        if (!$this->canViewTickets() || !$this->isStaff())
        {
            return $this->noPermission();
        }

        $this->assertPostOnly();

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
            'detail' => 'Ticket #' . $ticket['ticket_id'] . ' (front-end)',
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

        return $this->redirect($this->buildLink('tickets/view', null, ['ticket_id' => $ticket['ticket_id']]));
    }
}
