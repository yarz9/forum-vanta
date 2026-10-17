<?php

namespace XF\Authy;

use Authy\AuthyApi;

/**
 * @deprecated This provider is deprecated and may remove or stop working in the future.
 */
class Api extends AuthyApi
{
	protected $default_options = [];

	public function createApprovalRequest($authy_id, $message, $opts = [])
	{
		$authy_id = (string) $authy_id;
		return parent::createApprovalRequest($authy_id, $message, $opts);
	}

	public function verifyToken($authy_id, $token, $opts = [])
	{
		$authy_id = (string) $authy_id;
		$token = (string) $token;
		return parent::verifyToken($authy_id, $token, $opts);
	}

	public function getApprovalRequest($request_uuid)
	{
		$request_uuid = (string) $request_uuid;
		return parent::getApprovalRequest($request_uuid);
	}
}
