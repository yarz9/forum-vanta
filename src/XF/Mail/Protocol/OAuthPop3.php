<?php

namespace XF\Mail\Protocol;

use Laminas\Mail\Protocol\Pop3;

class OAuthPop3 extends Pop3
{
	public function login($user, $password, $tryApop = true)
	{
		$this->request("AUTH XOAUTH2 " . base64_encode("user=$user\1auth=Bearer $password\1\1"));
	}
}
