<?php

namespace Vanta\Loader\Service;

use XF\App;
use XF\Entity\User;

class Authenticator
{
    protected $app;
    protected int $qualifyingUpgradeId = 1;

    public function __construct(App $app)
    {
        $this->app = $app;
        $optionId = (int) $app->options()->VantaLoader->qualifying_upgrade_id;
        if ($optionId > 0) {
            $this->qualifyingUpgradeId = $optionId;
        }
    }

    public function setQualifyingUpgradeId(int $id): void
    {
        $this->qualifyingUpgradeId = $id;
    }

    public function getQualifyingUpgradeId(): int
    {
        return $this->qualifyingUpgradeId;
    }

    public function authenticate(string $usernameOrEmail, string $password): array
    {
        $result = [
            'success' => false,
            'error' => null,
            'error_key' => null,
            'user' => null,
            'entitlement' => null,
            'maintenance' => null,
        ];

        $config = $this->getMaintConfig();
        if ($config['enabled'] === '1') {
            $result['error'] = $config['message'] ?: 'The service is temporarily unavailable.';
            $result['error_key'] = 'maintenance';
            $result['maintenance'] = $config;
            return $result;
        }

        $loginResult = $this->app->auth()->login(
            $usernameOrEmail,
            $password,
            $this->getClientIp(),
            false
        );

        if (!$loginResult->isSuccess()) {
            $result['error'] = 'Invalid username or password.';
            $result['error_key'] = 'invalid_credentials';
            return $result;
        }

        $user = $this->app->findByAlias('XF:User', $loginResult->getUser()->getUserId());
        if (!$user) {
            $result['error'] = 'Invalid username or password.';
            $result['error_key'] = 'invalid_credentials';
            return $result;
        }

        if ($this->isAccountRestricted($user)) {
            $result['error'] = 'This account is restricted from loader access.';
            $result['error_key'] = 'account_restricted';
            return $result;
        }

        $entitlement = $this->checkEntitlement($user);
        if (!$entitlement['has']) {
            $result['error'] = $entitlement['reason'];
            $result['error_key'] = $entitlement['error_key'];
            $result['entitlement'] = $entitlement;
            return $result;
        }

        $result['success'] = true;
        $result['user'] = $user;
        $result['entitlement'] = $entitlement;
        return $result;
    }

    public function authenticateWithPassword(string $username, string $password): array
    {
        $login = $this->app->auth()->login($username, $password, $this->getClientIp(), false);

        if (!$login->isSuccess()) {
            return ['success' => false, 'error_key' => 'invalid_credentials'];
        }

        $user = $login->getUser();
        if (!$user) {
            return ['success' => false, 'error_key' => 'invalid_credentials'];
        }

        return [
            'success' => true,
            'user' => $user,
        ];
    }

    public function checkEntitlement(User $user): array
    {
        $entitlement = [
            'has' => false,
            'reason' => '',
            'error_key' => 'no_active_subscription',
            'is_permanent' => false,
            'upgrade_name' => '',
            'expiry_timestamp' => 0,
        ];

        $db = $this->app->db();

        $activeUpgrades = $db->fetchAll("
            SELECT ua.*, uupg.title AS upgrade_name
            FROM xf_user_upgrade_active ua
            LEFT JOIN xf_user_upgrade uupg ON ua.user_upgrade_id = uupg.user_upgrade_id
            WHERE ua.user_id = ? AND (ua.end_date = 0 OR ua.end_date > ?)
            ORDER BY ua.start_date DESC
            LIMIT 1
        ", [$user->getUser_id(), time()]);

        if (empty($activeUpgrades)) {
            $entitlement['reason'] = 'No active qualifying subscription.';
            return $entitlement;
        }

        $upgrade = $activeUpgrades[0];

        $allUpgrades = $db->fetchAll("
            SELECT user_upgrade_id, title, user_group_id, is_purchasable
            FROM xf_user_upgrade
            WHERE user_upgrade_id = ?
        ", [$this->qualifyingUpgradeId]);

        if (empty($allUpgrades)) {
            $entitlement['reason'] = 'You do not have an active qualifying subscription.';
            return $entitlement;
        }

        $qualifying = $allUpgrades[0];

        $activeUpgradeIds = array_column($activeUpgrades, 'user_upgrade_id');
        if (!in_array($this->qualifyingUpgradeId, $activeUpgradeIds)) {
            $entitlement['reason'] = 'You do not have a qualifying subscription.';
            return $entitlement;
        }

        if (!empty($upgrade['end_date']) && $upgrade['end_date'] <= time()) {
            $entitlement['reason'] = 'Your subscription has expired.';
            $entitlement['error_key'] = 'subscription_expired';
            $entitlement['expiry_timestamp'] = $upgrade['end_date'];
            return $entitlement;
        }

        $entitlement['has'] = true;
        $entitlement['is_permanent'] = ($upgrade['end_date'] == 0);
        $entitlement['upgrade_name'] = $upgrade['upgrade_name'] ?: $qualifying['title'];
        $entitlement['expiry_timestamp'] = $upgrade['end_date'];
        return $entitlement;
    }

    public function isAccountRestricted(User $user): bool
    {
        $db = $this->app->db();

        $ban = $db->fetchRow("
            SELECT * FROM xf_vanta_ban
            WHERE user_id = ? AND (expires IS NULL OR expires > ?)
            ORDER BY created DESC
            LIMIT 1
        ", [$user->getUser_id(), time()]);

        if ($ban) {
            return true;
        }

        if ($user->is_banned() && $user->get('ban_end_date') > time()) {
            return true;
        }

        return false;
    }

    public function issueSession(User $user, ?string $hwid = null): array
    {
        $db = $this->app->db();

        $sessionToken = bin2hex(random_bytes(16));
        $tokenHash = hash('sha256', $sessionToken);
        $deviceToken = null;

        if ($hwid) {
            $hwidHash = hash('sha256', $hwid);
            $existingDevice = $db->fetchRow("
                SELECT * FROM xf_vanta_device
                WHERE user_id = ? AND hwid_hash = ?
            ", [$user->getUser_id(), $hwidHash]);

            if ($existingDevice) {
                $db->update('xf_vanta_device', [
                    'last_seen' => time(),
                ], 'device_id = ?', $existingDevice['device_id']);
                $deviceToken = $existingDevice['hwid_hash'];
            } else {
                $db->insert('xf_vanta_device', [
                    'user_id' => $user->getUser_id(),
                    'hwid_hash' => $hwidHash,
                    'device_name' => 'Device ' . substr($hwidHash, 0, 8),
                    'created' => time(),
                    'last_seen' => time(),
                    'create_ip' => $this->getClientIp(),
                ]);
                $deviceToken = $hwidHash;
            }
        }

        $db->insert('xf_vanta_session', [
            'session_token' => $sessionToken,
            'user_id' => $user->getUser_id(),
            'token_hash' => $tokenHash,
            'created' => time(),
            'expires' => time() + 86400 * 30,
            'device_token' => $deviceToken,
            'login_ip' => $this->ipToLong($this->getClientIp()),
            'login_user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'last_activity' => time(),
        ]);

        $db->insert('xf_vanta_log', [
            'user_id' => $user->getUser_id(),
            'hwid_hash' => $deviceToken,
            'ip_address' => $this->getClientIp(),
            'event_type' => 'login',
            'detail' => 'Session issued',
            'timestamp' => time(),
        ]);

        return [
            'token' => $sessionToken,
            'expires' => time() + 86400 * 30,
            'device_bound' => $deviceToken !== null,
        ];
    }

    public function verifySession(string $sessionToken, ?string $hwid = null): array
    {
        $db = $this->app->db();

        $session = $db->fetchRow("
            SELECT s.*, u.username, u.user_id
            FROM xf_vanta_session s
            LEFT JOIN xf_user u ON s.user_id = u.user_id
            WHERE s.session_token = ? AND s.expires > ?
            LIMIT 1
        ", [$sessionToken, time()]);

        if (!$session) {
            return ['valid' => false, 'error_key' => 'session_expired'];
        }

        $db->update('xf_vanta_session', [
            'last_activity' => time(),
        ], 'session_token = ?', $sessionToken);

        $entitlement = $this->checkEntitlementFromUserId((int)$session['user_id']);

        if (!$entitlement['has']) {
            return [
                'valid' => false,
                'error_key' => $entitlement['error_key'] ?? 'no_active_subscription',
                'entitlement' => $entitlement,
            ];
        }

        if ($hwid) {
            $hwidHash = hash('sha256', $hwid);
            $device = $db->fetchRow("
                SELECT * FROM xf_vanta_device
                WHERE user_id = ? AND hwid_hash = ?
            ", [$session['user_id'], $hwidHash]);

            if (!$device) {
                return ['valid' => false, 'error_key' => 'device_mismatch'];
            }
        }

        return [
            'valid' => true,
            'user_id' => (int)$session['user_id'],
            'username' => $session['username'],
            'entitlement' => $entitlement,
            'device_bound' => $hwid !== null,
        ];
    }

    protected function checkEntitlementFromUserId(int $userId): array
    {
        $db = $this->app->db();

        $activeUpgrades = $db->fetchAll("
            SELECT ua.*, uupg.title AS upgrade_name
            FROM xf_user_upgrade_active ua
            LEFT JOIN xf_user_upgrade uupg ON ua.user_upgrade_id = uupg.user_upgrade_id
            WHERE ua.user_id = ? AND (ua.end_date = 0 OR ua.end_date > ?)
            ORDER BY ua.start_date DESC
            LIMIT 1
        ", [$userId, time()]);

        $entitlement = [
            'has' => false,
            'is_permanent' => false,
            'upgrade_name' => '',
            'expiry_timestamp' => 0,
            'error_key' => 'no_active_subscription',
        ];

        if (!empty($activeUpgrades) && in_array($this->qualifyingUpgradeId, array_column($activeUpgrades, 'user_upgrade_id'))) {
            $upgrade = $activeUpgrades[0];
            $entitlement['has'] = true;
            $entitlement['is_permanent'] = ($upgrade['end_date'] == 0);
            $entitlement['upgrade_name'] = $upgrade['upgrade_name'];
            $entitlement['expiry_timestamp'] = $upgrade['end_date'];
        }

        return $entitlement;
    }

    public function logout(string $sessionToken): void
    {
        $this->app->db()->delete('xf_vanta_session', 'session_token = ?', $sessionToken);
    }

    public function getMaintConfig(): array
    {
        $db = $this->app->db();
        $rows = $db->fetchPairs("SELECT setting_key, setting_value FROM xf_vanta_maint");

        $options = $this->app->options()->VantaLoader ?? null;

        return [
            'enabled' => $rows['enabled'] ?? '0',
            'message' => $rows['message'] ?? '',
            'min_version' => $rows['min_version'] ?? '',
            'download_url' => $rows['download_url'] ?? '',
            'support_url' => $options->support_url ?? 'https://vantacodes.xyz/',
            'purchase_url' => $options->purchase_url ?? 'https://vantacodes.xyz/account/upgrades/',
        ];
    }

    public function checkVersion(string $clientVersion): array
    {
        $config = $this->getMaintConfig();
        $minVersion = $config['min_version'] ?? '';

        if ($minVersion && version_compare($clientVersion, $minVersion, '<')) {
            return [
                'supported' => false,
                'min_version' => $minVersion,
                'download_url' => $config['download_url'],
                'message' => 'An update is required.',
            ];
        }

        return ['supported' => true];
    }

    public function isDeviceBanned(string $hwid): bool
    {
        if (!$hwid) {
            return false;
        }

        $hwidHash = hash('sha256', $hwid);
        $db = $this->app->db();

        $ban = $db->fetchRow("
            SELECT * FROM xf_vanta_ban
            WHERE hwid_hash = ? AND (expires IS NULL OR expires > ?)
            ORDER BY created DESC
            LIMIT 1
        ", [$hwidHash, time()]);

        return !empty($ban);
    }

    protected function getClientIp(): string
    {
        $request = $this->app->request();
        if ($request->getEnv('HTTP_CF_CONNECTING_IP')) {
            return $request->getEnv('HTTP_CF_CONNECTING_IP');
        }
        if ($request->getEnv('HTTP_X_FORWARDED_FOR')) {
            $ips = explode(',', $request->getEnv('HTTP_X_FORWARDED_FOR'));
            return trim($ips[0]);
        }
        return $request->getIp();
    }

    protected function ipToLong(string $ip): int
    {
        $long = ip2long($ip);
        return $long !== false ? $long : 0;
    }
}
