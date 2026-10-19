<?php

namespace Vanta\Loader\Admin\Controller;

use XF\Admin\Controller\AbstractController;

class Loader extends AbstractController
{
    public function actionIndex()
    {
        $this->assertAdminPermission('vantaLoaderAdmin');

        $db = \XF::db();

        $banCount = (int) $db->fetchOne("SELECT COUNT(*) FROM xf_vanta_ban WHERE (expires IS NULL OR expires > ?)", [time()]);
        $deviceCount = (int) $db->fetchOne("SELECT COUNT(*) FROM xf_vanta_device");
        $resetCount = (int) $db->fetchOne("SELECT COUNT(*) FROM xf_vanta_reset WHERE status = 'pending'");

        $viewParams = [
            'banCount' => $banCount,
            'deviceCount' => $deviceCount,
            'resetCount' => $resetCount,
        ];

        return $this->view('Vanta\Loader:Loader', 'vanta_loader', $viewParams);
    }

    public function actionUsers()
    {
        $this->assertAdminPermission('vantaLoaderAdmin');

        $db = \XF::db();

        $page = $this->filterPage();
        $perPage = 50;

        $total = (int) $db->fetchOne("SELECT COUNT(*) FROM xf_vanta_device");

        $hwidSelect = $db->getSchemaManager()->columnExists('xf_vanta_device', 'hwid')
            ? 'd.hwid'
            : "'' AS hwid";

        $versionSelect = $db->getSchemaManager()->columnExists('xf_vanta_device', 'loader_version')
            ? 'd.loader_version'
            : "'' AS loader_version";

        $hashSelect = $db->getSchemaManager()->columnExists('xf_vanta_device', 'loader_hash')
            ? 'd.loader_hash'
            : "'' AS loader_hash";

        $devices = $db->fetchAll($db->limit("
            SELECT d.device_id, d.user_id, d.hwid_hash, d.device_name, d.created, d.last_seen,
                {$hwidSelect}, {$versionSelect}, {$hashSelect}, u.username,
                (SELECT MAX(l.timestamp)
                 FROM xf_vanta_log l
                 WHERE l.user_id = d.user_id AND l.event_type = 'injection') AS last_injection
            FROM xf_vanta_device d
            LEFT JOIN xf_user u ON u.user_id = d.user_id
            ORDER BY d.last_seen DESC
        ", $perPage, ($page - 1) * $perPage));

        return $this->view('Vanta\Loader:Users', 'vanta_loader_users', [
            'devices' => $devices,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
        ]);
    }

    public function actionBans()
    {
        $this->assertAdminPermission('vantaLoaderBan');

        $db = \XF::db();

        $bans = $db->fetchAll("
            SELECT b.*
            FROM xf_vanta_ban b
            ORDER BY b.created DESC
        ");

        return $this->view('Vanta\Loader:Bans\List', 'vanta_loader_bans', ['bans' => $bans]);
    }

    public function actionBan()
    {
        $this->assertAdminPermission('vantaLoaderBan');

        if ($this->request->isPost())
        {
            $input = $this->filter([
                'user_id' => 'uint',
                'hwid' => 'str',
                'reason' => 'str',
                'expires_days' => 'uint',
                'type' => 'str',
            ]);

            if (empty($input['user_id']) && empty($input['hwid']))
            {
                return $this->error(\XF::phrase('vanta_loader_ban_requires_user_or_hwid'));
            }

            $db = \XF::db();
            $visitor = \XF::visitor();

            $hwidHash = !empty($input['hwid']) ? hash('sha256', $input['hwid']) : null;
            $expires = !empty($input['expires_days']) ? time() + ($input['expires_days'] * 86400) : null;

            $db->insert('xf_vanta_ban', [
                'user_id' => $input['user_id'] ?: null,
                'hwid_hash' => $hwidHash,
                'reason' => $input['reason'] ?: 'Manual ban',
                'created_by' => $visitor->user_id,
                'created_by_username' => $visitor->username,
                'created' => time(),
                'expires' => $expires,
                'type' => $input['type'] ?: 'manual',
            ]);

            $db->insert('xf_vanta_log', [
                'user_id' => $input['user_id'] ?: null,
                'hwid_hash' => $hwidHash,
                'ip_address' => '',
                'event_type' => 'ban',
                'detail' => $input['reason'],
                'timestamp' => time(),
            ]);

            return $this->redirect($this->buildLink('vanta-loader/bans'));
        }

        $userId = $this->filter('user_id', 'uint');

        return $this->view('Vanta\Loader:Bans\Edit', 'vanta_loader_ban_edit', [
            'userId' => $userId,
        ]);
    }

    public function actionUnban()
    {
        $this->assertAdminPermission('vantaLoaderBan');

        $banId = $this->filter('ban_id', 'uint');
        if (!$banId)
        {
            return $this->error(\XF::phrase('vanta_loader_requested_ban_not_found'));
        }

        $db = \XF::db();
        $db->delete('xf_vanta_ban', 'ban_id = ?', $banId);

        return $this->redirect($this->buildLink('vanta-loader/bans'));
    }

    public function actionResets()
    {
        $this->assertAdminPermission('vantaLoaderResets');

        $db = \XF::db();

        $resets = $db->fetchAll("
            SELECT r.*
            FROM xf_vanta_reset r
            ORDER BY r.created DESC
        ");

        return $this->view('Vanta\Loader:Resets\List', 'vanta_loader_resets', ['resets' => $resets]);
    }

    public function actionReset()
    {
        $this->assertAdminPermission('vantaLoaderResets');

        if ($this->request->isPost())
        {
            $input = $this->filter([
                'user_id' => 'uint',
                'hwid' => 'str',
                'reason' => 'str',
            ]);

            if (empty($input['user_id']) || empty($input['hwid']))
            {
                return $this->error(\XF::phrase('vanta_loader_reset_requires_user_and_hwid'));
            }

            $visitor = \XF::visitor();
            $db = \XF::db();
            $hwidHash = hash('sha256', $input['hwid']);

            $db->insert('xf_vanta_reset', [
                'user_id' => $input['user_id'],
                'hwid_hash' => $hwidHash,
                'reason' => $input['reason'] ?: 'Manual reset',
                'requested_by' => $visitor->user_id,
                'requested_by_username' => $visitor->username,
                'created' => time(),
                'status' => 'approved',
                'processed' => time(),
                'approved_by' => $visitor->user_id,
                'approved_by_username' => $visitor->username,
            ]);

            $db->delete('xf_vanta_device', 'user_id = ? AND hwid_hash = ?', [$input['user_id'], $hwidHash]);

            $db->insert('xf_vanta_log', [
                'user_id' => $input['user_id'],
                'hwid_hash' => $hwidHash,
                'ip_address' => '',
                'event_type' => 'reset_request',
                'detail' => $input['reason'],
                'timestamp' => time(),
            ]);

            return $this->redirect($this->buildLink('vanta-loader/resets'));
        }

        $userId = $this->filter('user_id', 'uint');

        return $this->view('Vanta\Loader:Resets\Edit', 'vanta_loader_reset_edit', [
            'userId' => $userId,
        ]);
    }

    public function actionApproveReset()
    {
        $this->assertAdminPermission('vantaLoaderResets');

        $resetId = $this->filter('reset_id', 'uint');
        if (!$resetId)
        {
            return $this->error(\XF::phrase('vanta_loader_requested_reset_not_found'));
        }

        $db = \XF::db();
        $reset = $db->fetchRow("SELECT * FROM xf_vanta_reset WHERE reset_id = ?", $resetId);

        if ($reset)
        {
            $visitor = \XF::visitor();

            $db->delete('xf_vanta_device',
                'user_id = ? AND hwid_hash = ?',
                [$reset['user_id'], $reset['hwid_hash']]
            );

            $db->update('xf_vanta_reset', [
                'status' => 'approved',
                'processed' => time(),
                'approved_by' => $visitor->user_id,
                'approved_by_username' => $visitor->username,
            ], 'reset_id = ?', $resetId);
        }

        return $this->redirect($this->buildLink('vanta-loader/resets'));
    }

    public function actionRejectReset()
    {
        $this->assertAdminPermission('vantaLoaderResets');

        $resetId = $this->filter('reset_id', 'uint');
        if (!$resetId)
        {
            return $this->error(\XF::phrase('vanta_loader_requested_reset_not_found'));
        }

        $visitor = \XF::visitor();
        $db = \XF::db();

        $db->update('xf_vanta_reset', [
            'status' => 'rejected',
            'processed' => time(),
            'approved_by' => $visitor->user_id,
            'approved_by_username' => $visitor->username,
        ], 'reset_id = ?', $resetId);

        return $this->redirect($this->buildLink('vanta-loader/resets'));
    }

    public function actionStatus()
    {
        $this->assertAdminPermission('vantaLoaderAdmin');

        $db = \XF::db();

        if ($this->request->isPost())
        {
            $input = $this->filter([
                'enabled' => 'bool',
                'message' => 'str',
                'min_version' => 'str',
                'download_url' => 'str',
            ]);

            $settings = [
                'enabled' => $input['enabled'] ? '1' : '0',
                'message' => $input['message'],
                'min_version' => $input['min_version'],
                'download_url' => $input['download_url'],
            ];

            $db->delete('xf_vanta_maint', 'setting_key IN (?, ?, ?, ?)', ['enabled', 'message', 'min_version', 'download_url']);
            foreach ($settings as $key => $value)
            {
                $db->insert('xf_vanta_maint', [
                    'setting_key' => $key,
                    'setting_value' => $value,
                ]);
            }

            return $this->redirect($this->buildLink('vanta-loader/status'));
        }

        $authenticator = new \Vanta\Loader\Service\Authenticator(\XF::app());
        $config = $authenticator->getMaintConfig();

        return $this->view('Vanta\Loader:Status', 'vanta_loader_status', ['config' => $config]);
    }
}
