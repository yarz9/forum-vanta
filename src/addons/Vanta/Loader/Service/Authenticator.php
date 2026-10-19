<?php

namespace Vanta\Loader\Service;

use XF\App;
use XF\Entity\User;
use XF\Service\User\LoginService;

class Authenticator
{
    protected $app;
    protected int $qualifyingUpgradeId = 1;

    public function __construct(App $app)
    {
        $this->app = $app;
        $optionId = (int) $app->options()->vanta_loader_qualifying_upgrade_id;
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

        /** @var LoginService $loginService */
        $loginService = $this->app->service(
            LoginService::class,
            $usernameOrEmail,
            $this->getClientIp()
        );

        $limitType = null;
        if ($loginService->isLoginLimited($limitType)) {
            $result['error'] = 'Your account has temporarily been locked due to failed login attempts.';
            $result['error_key'] = 'account_restricted';
            return $result;
        }

        $error = null;
        $user = $loginService->validate($password, $error);

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
        /** @var LoginService $loginService */
        $loginService = $this->app->service(LoginService::class, $username, $this->getClientIp());

        $limitType = null;
        if ($loginService->isLoginLimited($limitType)) {
            return ['success' => false, 'error_key' => 'account_restricted'];
        }

        $error = null;
        $user = $loginService->validate($password, $error);

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

        $upgrade = $db->fetchRow("
            SELECT ua.*, uupg.title AS upgrade_name
            FROM xf_user_upgrade_active ua
            LEFT JOIN xf_user_upgrade uupg ON ua.user_upgrade_id = uupg.user_upgrade_id
            WHERE ua.user_id = ?
                AND ua.user_upgrade_id = ?
                AND (ua.end_date = 0 OR ua.end_date > ?)
            ORDER BY ua.start_date DESC
            LIMIT 1
        ", [$user->user_id, $this->qualifyingUpgradeId, time()]);

        if (!$upgrade) {
            $expired = $db->fetchRow("
                SELECT end_date
                FROM xf_user_upgrade_active
                WHERE user_id = ? AND user_upgrade_id = ?
                ORDER BY end_date DESC
                LIMIT 1
            ", [$user->user_id, $this->qualifyingUpgradeId]);

            if ($expired && (int) $expired['end_date'] > 0) {
                $entitlement['reason'] = 'Your subscription has expired.';
                $entitlement['error_key'] = 'subscription_expired';
                $entitlement['expiry_timestamp'] = (int) $expired['end_date'];
            } else {
                $entitlement['reason'] = 'No active qualifying subscription.';
            }

            return $entitlement;
        }

        $entitlement['has'] = true;
        $entitlement['is_permanent'] = ((int) $upgrade['end_date'] === 0);
        $entitlement['upgrade_name'] = $upgrade['upgrade_name'] ?: '';
        $entitlement['expiry_timestamp'] = (int) $upgrade['end_date'];

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
        ", [$user->user_id, time()]);

        if ($ban) {
            return true;
        }

        if ($user->is_banned)
        {
            $nativeBan = $db->fetchRow("
                SELECT * FROM xf_user_ban
                WHERE user_id = ? AND (end_date = 0 OR end_date > ?)
            ", [$user->user_id, time()]);

            if ($nativeBan)
            {
                return true;
            }
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
            ", [$user->user_id, $hwidHash]);

            if ($existingDevice) {
                $deviceUpdate = [
                    'last_seen' => time(),
                ];
                if ($this->supportsHwidColumn('xf_vanta_device')) {
                    $deviceUpdate['hwid'] = substr($hwid, 0, 64);
                }
                $db->update('xf_vanta_device', $deviceUpdate, 'device_id = ?', $existingDevice['device_id']);
                $deviceToken = $existingDevice['hwid_hash'];
            } else {
                $deviceData = [
                    'user_id' => $user->user_id,
                    'hwid_hash' => $hwidHash,
                    'device_name' => 'Device ' . substr($hwidHash, 0, 8),
                    'created' => time(),
                    'last_seen' => time(),
                    'create_ip' => $this->getClientIp(),
                ];
                if ($this->supportsHwidColumn('xf_vanta_device')) {
                    $deviceData['hwid'] = substr($hwid, 0, 64);
                }
                $db->insert('xf_vanta_device', $deviceData);
                $deviceToken = $hwidHash;
            }
        }

        $db->insert('xf_vanta_session', [
            'session_token' => $sessionToken,
            'user_id' => $user->user_id,
            'token_hash' => $tokenHash,
            'created' => time(),
            'expires' => time() + 86400 * 30,
            'device_token' => $deviceToken,
            'login_ip' => $this->ipToLong($this->getClientIp()),
            'login_user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'last_activity' => time(),
        ]);

        $loginLog = [
            'user_id' => $user->user_id,
            'hwid_hash' => $deviceToken,
            'ip_address' => $this->getClientIp(),
            'event_type' => 'login',
            'detail' => 'Session issued',
            'timestamp' => time(),
        ];
        if ($hwid && $this->supportsHwidColumn('xf_vanta_log')) {
            $loginLog['hwid'] = substr($hwid, 0, 64);
        }
        $db->insert('xf_vanta_log', $loginLog);

        return [
            'token' => $sessionToken,
            'expires' => time() + 86400 * 30,
            'device_bound' => $deviceToken !== null,
        ];
    }

    // The raw HWID is only stored once the 1.0.1 upgrade has added the
    // column; this keeps logins working during the deployment window.
    protected function supportsHwidColumn(string $table): bool
    {
        static $cache = [];

        if (!isset($cache[$table])) {
            $cache[$table] = $this->app->db()->getSchemaManager()->columnExists($table, 'hwid');
        }

        return $cache[$table];
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
        $entitlement = [
            'has' => false,
            'is_permanent' => false,
            'upgrade_name' => '',
            'expiry_timestamp' => 0,
            'error_key' => 'no_active_subscription',
        ];

        $db = $this->app->db();

        $upgrade = $db->fetchRow("
            SELECT ua.*, uupg.title AS upgrade_name
            FROM xf_user_upgrade_active ua
            LEFT JOIN xf_user_upgrade uupg ON ua.user_upgrade_id = uupg.user_upgrade_id
            WHERE ua.user_id = ?
                AND ua.user_upgrade_id = ?
                AND (ua.end_date = 0 OR ua.end_date > ?)
            ORDER BY ua.start_date DESC
            LIMIT 1
        ", [$userId, $this->qualifyingUpgradeId, time()]);

        if (!$upgrade) {
            return $entitlement;
        }

        $entitlement['has'] = true;
        $entitlement['is_permanent'] = ((int) $upgrade['end_date'] === 0);
        $entitlement['upgrade_name'] = $upgrade['upgrade_name'] ?: '';
        $entitlement['expiry_timestamp'] = (int) $upgrade['end_date'];

        return $entitlement;
    }

    public function logout(string $sessionToken): void
    {
        $this->app->db()->delete('xf_vanta_session', 'session_token = ?', $sessionToken);
    }

    /**
     * Store the hardware/software fingerprint the loader sends at sign-in.
     * Only a fixed set of keys is kept so the loader cannot inject arbitrary
     * payloads into the database.
     */
    public function recordDeviceSpecs(int $userId, ?string $hwid, string $specsJson): void
    {
        if (!$userId || !$hwid || $specsJson === '')
        {
            return;
        }

        $db = $this->app->db();
        if (!$db->getSchemaManager()->columnExists('xf_vanta_device', 'specs'))
        {
            return;
        }

        $decoded = json_decode($specsJson, true);
        if (!is_array($decoded))
        {
            return;
        }

        $allowed = [
            'cpu', 'gpu', 'gpu_driver', 'ram', 'os', 'motherboard',
            'pc_name', 'username', 'cores', 'threads', 'disk',
            'resolution', 'uptime',
        ];

        $clean = [];
        foreach ($allowed AS $key)
        {
            if (!array_key_exists($key, $decoded))
            {
                continue;
            }

            $value = $decoded[$key];
            if (is_string($value))
            {
                $clean[$key] = mb_substr(trim($value), 0, 120);
            }
            else if (is_int($value) || is_float($value))
            {
                $clean[$key] = $value;
            }
        }

        if (!$clean)
        {
            return;
        }

        $db->update('xf_vanta_device', [
            'specs' => json_encode($clean),
            'last_seen' => time(),
        ], 'user_id = ? AND hwid_hash = ?', [$userId, hash('sha256', $hwid)]);
    }

    public function getMaintConfig(): array
    {
        $db = $this->app->db();
        $rows = $db->fetchPairs("SELECT setting_key, setting_value FROM xf_vanta_maint");

        $options = $this->app->options();

        return [
            'enabled' => $rows['enabled'] ?? '0',
            'message' => $rows['message'] ?? '',
            'min_version' => $rows['min_version'] ?? '',
            'download_url' => $rows['download_url'] ?? '',
            'support_url' => $options->vanta_loader_support_url ?: 'https://vantacodes.xyz/',
            'purchase_url' => $options->vanta_loader_purchase_url ?: 'https://vantacodes.xyz/account/upgrades/',
        ];
    }

    public function checkVersion(string $clientVersion): array
    {
        $config = $this->getMaintConfig();
        $minVersion = $config['min_version'] ?? '';

        if ($minVersion && version_compare($clientVersion, $minVersion, '!=')) {
            return [
                'supported' => false,
                'min_version' => $minVersion,
                'download_url' => $config['download_url'],
                'message' => 'Loader version mismatch.',
            ];
        }

        return ['supported' => true];
    }

    public function getPayloadUrl(): string
    {
        // The admin controls this from the Loader status page ("Update
        // Download URL"), so the DLL link can be changed at any time
        // without a loader update. The add-on option is kept as fallback.
        $config = $this->getMaintConfig();
        $downloadUrl = trim((string) ($config['download_url'] ?? ''));

        if ($downloadUrl !== '')
        {
            return $downloadUrl;
        }

        $options = $this->app->options();

        return trim((string) ($options->vanta_loader_payload_url ?? ''));
    }

    public function getPayloadInfo(): ?array
    {
        $path = \XF::getAddOnDirectory() . '/Vanta/Loader/payload/csgocheat.dll';

        if (!is_file($path))
        {
            return null;
        }

        $data = file_get_contents($path);
        if ($data === false || $data === '')
        {
            return null;
        }

        return [
            'data' => $data,
            'hash' => hash('sha256', $data),
            'size' => strlen($data),
        ];
    }

    public static function payloadKey(string $sessionToken, string $hwid): string
    {
        return hash('sha256', 'vanta-payload-v1|' . $sessionToken . '|' . $hwid, true);
    }

    // Encrypts the payload with a per-session key so a captured response
    // cannot be replayed to recover the DLL offline. Prefers AES-256-GCM
    // (openssl or libsodium, PHP 8+); if neither is available, returns null
    // and the payload is delivered over HTTPS only (see the controller).
    public function encryptPayload(string $data, string $sessionToken, string $hwid): ?array
    {
        $key = self::payloadKey($sessionToken, $hwid);

        if (extension_loaded('openssl') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true))
        {
            $nonce = random_bytes(12);
            $tag = '';
            $ciphertext = openssl_encrypt(
                $data,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                '',
                16
            );

            if ($ciphertext !== false)
            {
                return [
                    'cipher' => 'aes-256-gcm',
                    'blob' => $nonce . $tag . $ciphertext,
                ];
            }
        }

        if (extension_loaded('sodium')
            && function_exists('sodium_crypto_aead_aes256gcm_is_available')
            && sodium_crypto_aead_aes256gcm_is_available())
        {
            // sodium appends a 16-byte auth tag to the ciphertext.
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);
            $cipherAndTag = sodium_crypto_aead_aes256gcm_encrypt(
                $data,
                '',
                $key,
                $nonce,
                ''
            );

            $tag = substr($cipherAndTag, -16);
            $ciphertext = substr($cipherAndTag, 0, -16);

            return [
                'cipher' => 'aes-256-gcm',
                'blob' => $nonce . $tag . $ciphertext,
            ];
        }

        return null;
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

        $cfIp = $request->getServer('HTTP_CF_CONNECTING_IP');
        if ($cfIp) {
            return $cfIp;
        }

        $forwardedFor = $request->getServer('HTTP_X_FORWARDED_FOR');
        if ($forwardedFor) {
            $ips = explode(',', $forwardedFor);
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
