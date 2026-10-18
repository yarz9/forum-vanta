<?php

namespace Vanta\Loader\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Loader extends AbstractController
{
    protected function assertLoaderPermission(string $permission)
    {
        $visitor = \XF::visitor();

        if ($visitor->is_admin) {
            return;
        }

        if ($visitor->hasPermission('VantaLoader', $permission)) {
            return;
        }

        $this->error(\XF::phrase('you_do_not_have_admin_permissions'));
    }

    public function actionIndex()
    {
        $this->assertLoaderPermission('vanta_loader_admin');

        $db = \XF::db();

        $banCount = (int) $db->fetchOne("SELECT COUNT(*) FROM xf_vanta_ban WHERE (expires IS NULL OR expires > ?)", [time()]);
        $deviceCount = (int) $db->fetchOne("SELECT COUNT(*) FROM xf_vanta_device");
        $resetCount = (int) $db->fetchone("SELECT COUNT(*) FROM xf_vanta_reset WHERE status = 'pending'");

        $viewParams = [
            'banCount' => $banCount,
            'deviceCount' => $deviceCount,
            'resetCount' => $resetCount,
        ];

        return $this->view('Vanta\Loader:Loader', 'vanta_loader', $viewParams);
    }

    public function actionBans()
    {
        $this->assertLoaderPermission('vanta_loader_ban');

        $db = \XF::db();

        $bans = $db->fetchAll("
            SELECT b.*, u.username AS creator_username
            FROM xf_vanta_ban b
            LEFT JOIN xf_user u ON b.created_by = u.user_id
            ORDER BY b.created DESC
        ");

        return $this->view('Vanta\Loader:Bans\List', 'vanta_loader_bans', ['bans' => $bans]);
    }

    public function actionBan()
    {
        $this->assertLoaderPermission('vanta_loader_ban');

        if ($this->request->getMethod() === 'POST') {
            $input = $this->filter([
                'user_id' => 'uint',
                'hwid' => 's',
                'reason' => 's',
                'expires' => 'uint',
                'type' => 's',
            ]);

            if (empty($input['user_id']) && empty($input['hwid'])) {
                return $this->error('A user ID or HWID is required.');
            }

            $db = \XF::db();
            $visitor = \XF::visitor();

            $hwidHash = !empty($input['hwid']) ? hash('sha256', $input['hwid']) : null;
            $expires = !empty($input['expires']) ? time() + $input['expires'] : null;

            $db->insert('xf_vanta_ban', [
                'user_id' => $input['user_id'] ?: null,
                'hwid_hash' => $hwidHash,
                'reason' => $input['reason'],
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
        $user = $userId ? $this->app->find('XF:User', $userId) : null;

        return $this->view('Vanta\Loader:Bans\Edit', 'vanta_loader_ban_edit', [
            'userId' => $userId,
            'user' => $user,
        ]);
    }

    public function actionUnban(\Vanta\Loader\Entity\Ban $ban)
    {
        $this->assertLoaderPermission('vanta_loader_ban');

        $db = \XF::db();
        $db->delete('xf_vanta_ban', 'ban_id = ?', $ban->get('ban_id'));
        return $this->redirect($this->buildLink('vanta-loader/bans'));
    }

    public function actionResets()
    {
        $this->assertLoaderPermission('vanta_loader_hwid_reset');

        $db = \XF::db();

        $resets = $db->fetchAll("
            SELECT r.*, u.username, a.username AS processed_by_username
            FROM xf_vanta_reset r
            LEFT JOIN xf_user u ON r.user_id = u.user_id
            LEFT JOIN xf_user a ON r.approved_by = a.user_id
            ORDER BY r.created DESC
        ");

        return $this->view('Vanta\Loader:Resets\List', 'vanta_loader_resets', ['resets' => $resets]);
    }

    public function actionReset()
    {
        $this->assertLoaderPermission('vanta_loader_hwid_reset');

        if ($this->request->getMethod() === 'POST') {
            $input = $this->filter([
                'user_id' => 'uint',
                'hwid' => 's',
                'reason' => 's',
            ]);

            if (empty($input['user_id']) || empty($input['hwid'])) {
                return $this->error('User ID and HWID are required.');
            }

            $visitor = \XF::visitor();
            $db = \XF::db();
            $hwidHash = hash('sha256', $input['hwid']);

            $isAdmin = $visitor->is_admin;

            $db->insert('xf_vanta_reset', [
                'user_id' => $input['user_id'],
                'hwid_hash' => $hwidHash,
                'reason' => $input['reason'],
                'requested_by' => $visitor->user_id,
                'requested_by_username' => $visitor->username,
                'created' => time(),
                'status' => $isAdmin ? 'approved' : 'pending',
            ]);

            $db->insert('xf_vanta_log', [
                'user_id' => $input['user_id'],
                'hwid_hash' => $hwidHash,
                'ip_address' => '',
                'event_type' => 'reset_request',
                'detail' => $input['reason'],
                'timestamp' => time(),
            ]);

            if ($isAdmin) {
                $db->delete('xf_vanta_device', 'user_id = ? AND hwid_hash = ?', [$input['user_id'], $hwidHash]);
                $db->update('xf_vanta_reset',
                    ['processed' => time(), 'approved_by' => $visitor->user_id, 'approved_by_username' => $visitor->username],
                    'user_id = ? AND hwid_hash = ? AND status = ?',
                    [$input['user_id'], $hwidHash, 'approved']
                );
            }

            return $this->redirect($this->buildLink('vanta-loader/resets'));
        }

        $userId = $this->filter('user_id', 'uint');
        $user = $userId ? $this->app->find('XF:User', $userId) : null;

        return $this->view('Vanta\Loader:Resets\Edit', 'vanta_loader_reset_edit', [
            'userId' => $userId,
            'user' => $user,
        ]);
    }

    public function actionApproveReset(\Vanta\Loader\Entity\Reset $reset)
    {
        $this->assertLoaderPermission('vanta_loader_hwid_reset');

        $visitor = \XF::visitor();
        $db = \XF::db();

        $db->delete('xf_vanta_device',
            'user_id = ? AND hwid_hash = ?',
            [$reset->get('user_id'), $reset->get('hwid_hash')]
        );

        $db->update('xf_vanta_reset', [
            'status' => 'approved',
            'processed' => time(),
            'approved_by' => $visitor->user_id,
            'approved_by_username' => $visitor->username,
        ], 'reset_id = ?', $reset->get('reset_id'));

        return $this->redirect($this->buildLink('vanta-loader/resets'));
    }

    public function actionRejectReset(\Vanta\Loader\Entity\Reset $reset)
    {
        $this->assertLoaderPermission('vanta_loader_hwid_reset');

        $visitor = \XF::visitor();
        $db = \XF::db();

        $db->update('xf_vanta_reset', [
            'status' => 'rejected',
            'processed' => time(),
            'approved_by' => $visitor->user_id,
            'approved_by_username' => $visitor->username,
        ], 'reset_id = ?', $reset->get('reset_id'));

        return $this->redirect($this->buildLink('vanta-loader/resets'));
    }

    public function actionStatus()
    {
        $this->assertLoaderPermission('vanta_loader_admin');

        if ($this->request->getMethod() === 'POST') {
            $input = $this->filter([
                'enabled' => 'bool',
                'message' => 's',
                'min_version' => 's',
                'download_url' => 's',
                'support_url' => 's',
            ]);

            $db = \XF::db();

            $settings = [
                'enabled' => $input['enabled'] ? '1' : '0',
                'message' => $input['message'],
                'min_version' => $input['min_version'],
                'download_url' => $input['download_url'],
                'support_url' => $input['support_url'],
            ];

            $db->delete('xf_vanta_maint');
            foreach ($settings as $key => $value) {
                $db->insert('xf_vanta_maint', [
                    'setting_key' => $key,
                    'setting_value' => $value,
                ]);
            }

            return $this->redirect($this->buildLink('vanta-loader'));
        }

        $authenticator = new \Vanta\Loader\Service\Authenticator(\XF::app());
        $config = $authenticator->getMaintConfig();

        return $this->view('Vanta\Loader:Status', 'vanta_loader_status', ['config' => $config]);
    }
}
