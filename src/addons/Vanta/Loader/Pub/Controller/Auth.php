<?php

namespace Vanta\Loader\Pub\Controller;

use XF\Mvc\Controller;
use XF\Mvc\ParameterBag;
use XF\Service\User\LoginService;
use Vanta\Loader\Service\Authenticator;

class Auth extends Controller
{
    public function checkCsrfIfNeeded($action, ParameterBag $params)
    {
    }

    public function actionLogin()
    {
        $input = $this->filter([
            'username' => 'str',
            'password' => 'str',
            'hwid' => 'str',
            'client_version' => 'str',
            'specs' => 'str',
            'loader_hash' => 'str',
            'loader_version' => 'str',
            'analysis' => 'str',
        ]);

        $result = $this->authenticate($input);
        return $this->responseJson($result);
    }

    protected function authenticate(array $input): array
    {
        $app = \XF::app();
        $authenticator = new Authenticator($app);

        $username = trim($input['username']);
        $password = $input['password'];
        $hwid = trim($input['hwid']);

        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'error' => 'Username and password are required.',
                'error_key' => 'invalid_credentials',
            ];
        }

        // Step 1: Authenticate credentials using XenForo's login system
        $loginResult = $this->attemptLogin($username, $password);

        if (!$loginResult['success']) {
            return $loginResult;
        }

        $user = $loginResult['user'];

        // Step 2: Check maintenance mode
        $config = $authenticator->getMaintConfig();
        if ($config['enabled'] === '1') {
            return [
                'success' => false,
                'error' => $config['message'] ?: 'The service is temporarily unavailable.',
                'error_key' => 'maintenance',
                'maintenance' => $config,
            ];
        }

        // Step 3: Check account restrictions (XenForo native bans)
        if ($authenticator->isAccountRestricted($user)) {
            return [
                'success' => false,
                'error' => 'This account is restricted from loader access.',
                'error_key' => 'account_restricted',
            ];
        }

        // Step 4: Check device ban
        if ($authenticator->isDeviceBanned($hwid)) {
            return [
                'success' => false,
                'error' => 'This device is blocked.',
                'error_key' => 'loader_banned',
            ];
        }

        // Step 5: Check entitlement (qualifying upgrade)
        $entitlement = $authenticator->checkEntitlement($user);

        if (!$entitlement['has']) {
            return [
                'success' => false,
                'error' => $entitlement['reason'],
                'error_key' => $entitlement['error_key'],
                'entitlement' => $entitlement,
            ];
        }

        // Step 6: Issue session token
        $session = $authenticator->issueSession($user, $hwid);

        // Hardware fingerprint (CPU/GPU/RAM/OS...) for staff review in tickets.
        $authenticator->recordDeviceSpecs($user->user_id, $hwid, $input['specs'] ?? '');

        // Loader build integrity information and anti-analysis flags.
        $authenticator->recordLoaderInfo(
            $user->user_id,
            $hwid,
            $input['loader_hash'] ?? '',
            $input['loader_version'] ?? ''
        );

        $this->recordAnalysisEvent($user->user_id, $hwid, $input['analysis'] ?? '');

        return [
            'success' => true,
            'user_id' => $user->user_id,
            'username' => $user->username,
            'session_token' => $session['token'],
            'expires' => $session['expires'],
            'device_bound' => $session['device_bound'],
            'entitlement' => [
                'has_qualifying_upgrade' => $entitlement['has'],
                'is_permanent' => $entitlement['is_permanent'],
                'upgrade_name' => $entitlement['upgrade_name'],
                'expiry_timestamp' => $entitlement['expiry_timestamp'],
            ],
            'purchase_url' => $config['purchase_url'],
            'support_url' => $config['support_url'],
        ];
    }

    protected function attemptLogin(string $username, string $password): array
    {
        $ip = $this->getClientIp();

        /** @var LoginService $loginService */
        $loginService = $this->service(LoginService::class, $username, $ip);

        $limitType = null;
        if ($loginService->isLoginLimited($limitType)) {
            return [
                'success' => false,
                'error' => 'Your account has temporarily been locked due to failed login attempts.',
                'error_key' => 'account_restricted',
            ];
        }

        $error = null;
        $user = $loginService->validate($password, $error);

        if ($user) {
            return [
                'success' => true,
                'user' => $user,
            ];
        }

        return [
            'success' => false,
            'error' => $this->getErrorMessage('invalid_credentials'),
            'error_key' => 'invalid_credentials',
        ];
    }

    public function actionVerify()
    {
        $input = $this->filter([
            'session_token' => 'str',
            'hwid' => 'str',
            'client_version' => 'str',
            'payload_hash' => 'str',
            'specs' => 'str',
            'loader_hash' => 'str',
            'loader_version' => 'str',
            'analysis' => 'str',
        ]);

        $authenticator = new Authenticator(\XF::app());
        $result = $authenticator->verifySession($input['session_token'], $input['hwid']);

        if (!$result['valid']) {
            return $this->responseJson([
                'success' => false,
                'error' => $this->getErrorMessage($result['error_key'] ?? 'session_expired'),
                'error_key' => $result['error_key'] ?? 'session_expired',
                'entitlement' => $result['entitlement'] ?? null,
            ]);
        }

        $authenticator->recordDeviceSpecs((int) $result['user_id'], $input['hwid'], $input['specs'] ?? '');

        $authenticator->recordLoaderInfo(
            (int) $result['user_id'],
            $input['hwid'],
            $input['loader_hash'] ?? '',
            $input['loader_version'] ?? ''
        );

        $this->recordAnalysisEvent(
            (int) $result['user_id'],
            $input['hwid'],
            $input['analysis'] ?? ''
        );

        $versionCheck = $authenticator->checkVersion($input['client_version']);
        if (!$versionCheck['supported']) {
            return $this->responseJson([
                'success' => false,
                'error' => 'An update is required.',
                'error_key' => 'unsupported_version',
                'min_version' => $versionCheck['min_version'],
                'download_url' => $versionCheck['download_url'],
            ]);
        }

        $response = [
            'success' => true,
            'user_id' => $result['user_id'],
            'username' => $result['username'],
            'entitlement' => $result['entitlement'],
            'device_bound' => $result['device_bound'],
        ];

        // An admin-configured direct link takes priority over the bundled
        // payload. The loader fetches it at sign-in and keeps it in memory.
        $payloadUrl = $authenticator->getPayloadUrl();
        if ($payloadUrl !== '')
        {
            $response['payload_url'] = $payloadUrl;
        }
        else
        {
            $payload = $authenticator->getPayloadInfo();
            if ($payload)
            {
                $response['payload_hash'] = $payload['hash'];
                $response['payload_size'] = $payload['size'];

                // The loader already has these exact bytes - no transfer.
                if ($input['payload_hash'] !== '' && hash_equals($payload['hash'], $input['payload_hash']))
                {
                    $response['payload_current'] = true;
                }
                else
                {
                    $envelope = $authenticator->encryptPayload(
                        $payload['data'],
                        $input['session_token'],
                        $input['hwid']
                    );

                    if ($envelope === null)
                    {
                        // No cryptography extension is available. The payload is
                        // still served over HTTPS; this is a safe fallback only
                        // on servers that truly have no crypto extension.
                        $response['payload_b64'] = base64_encode($payload['data']);
                    }
                    else
                    {
                        $response['payload_cipher'] = $envelope['cipher'];
                        $response['payload_b64'] = base64_encode($envelope['blob']);
                    }
                }
            }
        }

        $user = \XF::em()->find('XF:User', (int) $result['user_id']);
        if ($user)
        {
            $builds = $authenticator->getBuildOptions($user);
            $response['allowed_builds'] = implode(',', array_keys($builds));
            $response['build_live_url'] = (string) ($builds['live'] ?? '');

            if (!empty($builds['beta']))
            {
                $response['build_beta_url'] = $builds['beta'];
            }

            if (!empty($builds['laff']))
            {
                $response['build_laff_url'] = $builds['laff'];
            }
        }

        return $this->responseJson($response);
    }

    public function actionLogout()
    {
        $input = $this->filter([
            'session_token' => 'str',
        ]);

        $authenticator = new Authenticator(\XF::app());
        $authenticator->logout($input['session_token']);

        return $this->responseJson(['success' => true]);
    }

    public function actionStatus()
    {
        $authenticator = new Authenticator(\XF::app());
        $config = $authenticator->getMaintConfig();

        return $this->responseJson([
            'enabled' => $config['enabled'] === '1',
            'message' => $config['message'],
            'min_version' => $config['min_version'],
            'download_url' => $config['download_url'],
            'support_url' => $config['support_url'],
            'purchase_url' => $config['purchase_url'],
        ]);
    }

    public function actionReport()
    {
        $input = $this->filter([
            'session_token' => 'str',
            'hwid' => 'str',
            'event' => 'str',
            'detail' => 'str',
            'client_version' => 'str',
            'specs' => 'str',
            'loader_hash' => 'str',
            'loader_version' => 'str',
            'analysis' => 'str',
        ]);

        $authenticator = new Authenticator(\XF::app());
        $result = $authenticator->verifySession($input['session_token'], $input['hwid']);

        if (!$result['valid']) {
            return $this->responseJson([
                'success' => false,
                'error_key' => $result['error_key'] ?? 'session_expired',
            ]);
        }

        $authenticator->recordDeviceSpecs((int) $result['user_id'], $input['hwid'], $input['specs'] ?? '');

        $authenticator->recordLoaderInfo(
            (int) $result['user_id'],
            $input['hwid'],
            $input['loader_hash'] ?? '',
            $input['loader_version'] ?? ''
        );

        $this->recordAnalysisEvent(
            (int) $result['user_id'],
            $input['hwid'],
            $input['analysis'] ?? ''
        );

        // Only a few known events are recorded; unknown events are ignored
        // so the endpoint stays forward-compatible with loader updates.
        if (in_array($input['event'], ['injection', 'analysis', 'tamper'], true)) {
            $logData = [
                'user_id' => $result['user_id'],
                'hwid_hash' => $input['hwid'] ? hash('sha256', $input['hwid']) : null,
                'ip_address' => null,
                'event_type' => $input['event'],
                'detail' => trim($input['detail']) ?: ($input['event'] === 'injection' ? 'Injected' : ''),
                'timestamp' => time(),
            ];

            if ($input['hwid'] && \XF::db()->getSchemaManager()->columnExists('xf_vanta_log', 'hwid')) {
                $logData['hwid'] = substr($input['hwid'], 0, 64);
            }

            \XF::db()->insert('xf_vanta_log', $logData);
        }

        return $this->responseJson(['success' => true]);
    }

    protected function responseJson(array $data)
    {
        $reply = $this->view('', '', ['innerContent' => $data]);
        $reply->setResponseType('json');
        $reply->setViewOption('skipDefaultJsonParams', true);

        return $reply;
    }

    protected function recordAnalysisEvent(int $userId, ?string $hwid, string $analysis): void
    {
        if ($userId <= 0 || $analysis === '')
        {
            return;
        }

        $db = \XF::db();
        $logData = [
            'user_id' => $userId,
            'hwid_hash' => $hwid ? hash('sha256', $hwid) : null,
            'ip_address' => null,
            'event_type' => 'analysis',
            'detail' => mb_substr($analysis, 0, 255),
            'timestamp' => time(),
        ];

        if ($hwid && $db->getSchemaManager()->columnExists('xf_vanta_log', 'hwid'))
        {
            $logData['hwid'] = substr($hwid, 0, 64);
        }

        $db->insert('xf_vanta_log', $logData);
    }

    protected function getClientIp(): string
    {
        $request = \XF::app()->request();

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

    protected function getErrorMessage(string $key): string
    {
        $messages = [
            'invalid_credentials' => 'Invalid username or password.',
            'account_restricted' => 'This account is restricted from loader access.',
            'no_active_subscription' => 'You do not have an active qualifying subscription.',
            'subscription_expired' => 'Your subscription has expired.',
            'device_mismatch' => 'This device is not recognized.',
            'loader_banned' => 'This device has been blocked.',
            'session_expired' => 'Your session has expired. Please sign in again.',
            'maintenance' => 'The service is temporarily unavailable.',
            'unsupported_version' => 'An update is required.',
        ];

        return $messages[$key] ?? 'Authentication failed.';
    }
}
