<?php

namespace XF\Mail\Protocol;

use Laminas\Mail\Protocol\Imap;

class OAuthImap extends Imap
{
	public function login($user, $password)
	{
		$tokens = [
			'XOAUTH2', base64_encode(
				'user=' . $user . "\1" .
				'auth=Bearer ' . $password . "\1\1"
			),
		];
		return $this->requestAndResponse('AUTHENTICATE', $tokens, true);
	}
}
