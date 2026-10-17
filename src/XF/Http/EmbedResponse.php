<?php

namespace XF\Http;

class EmbedResponse extends Response
{
	public function getCookie($name, $addPrefix = false)
	{
		return [];
	}

	public function getCookies()
	{
		return [];
	}

	public function getCookiesExcept(array $skip, $addPrefix = false)
	{
		return [];
	}

	public function setCookie($name, $value, $lifetime = 0, $secure = null, $httpOnly = true, $sameSite = null)
	{
		return $this;
	}

	public function setCookieRaw($name, $value = '', $lifetime = 0, $path = '/', $domain = '', $secure = false, $httpOnly = true, $sameSite = null)
	{
		return $this;
	}

	public function removeCookie($name)
	{
		return $this;
	}
}
