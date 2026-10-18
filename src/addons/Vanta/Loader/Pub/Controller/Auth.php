<?php

namespace Vanta\Loader\Pub\Controller;

use XF\Mvc\Controller;
use Vanta\Loader\Service\Authenticator;

class Auth extends Controller
{
    public function actionLogin()
    {
        $input = $this->filter([
            'username' => 's',
            'password' => 's',
            'hwid' => 's',
            'client_version' => 's',
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

        return [
            'success' => true,
            'user_id' => $user->getUserId(),
            'username' => $user->getUsername(),
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
        $app = \XF::app();
        $ip = $this->getClientIp();

        $login = $app->auth()->login($username, $password, $ip, false);

        if ($login->isSuccess()) {
            $user = $login->getUser();
            if ($user) {
                return [
                    'success' => true,
                    'user' => $user,
                ];
            }
        }

        $errors = $login->getErrors();
        $errorKey = 'invalid_credentials';

        if (!empty($errors['error_key'])) {
            $errorKey = $errors['error_key'];
        } elseif (!empty($errors['error'])) {
            $errorKey = $errors['error'];
        }

        return [
            'success' => false,
            'error' => $this->getErrorMessage($errorKey),
            'error_key' => $errorKey,
        ];
    }

    public function actionVerify()
    {
        $input = $this->filter([
            'session_token' => 's',
            'hwid' => 's',
            'client_version' => 's',
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

        return $this->responseJson([
            'success' => true,
            'user_id' => $result['user_id'],
            'username' => $result['username'],
            'entitlement' => $result['entitlement'],
            'device_bound' => $result['device_bound'],
        ]);
    }

    public function actionLogout()
    {
        $input = $this->filter([
            'session_token' => 's',
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

    protected function getClientIp(): string
    {
        $request = \XF::app()->request();
        if ($request->getEnv('HTTP_CF_CONNECTING_IP')) {
            return $request->getEnv('HTTP_CF_CONNECTING_IP');
        }
        if ($request->getEnv('HTTP_X_FORWARDED_FOR')) {
            $ips = explode(',', $request->getEnv('HTTP_X_FORWARDED_FOR'));
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
