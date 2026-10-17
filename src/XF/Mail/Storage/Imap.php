<?php

namespace XF\Mail\Storage;

use Laminas\Mail\Exception\RuntimeException;
use XF\Mail\Protocol\OAuthImap;

use function intval;

class Imap extends \Laminas\Mail\Storage\Imap
{
	public static function setupFromHandler(array $handler): self
	{
		$config = [
			'host' => $handler['host'],
			'port' => $handler['port'] ? intval($handler['port']) : null,
			'ssl' => $handler['encryption'] ? strtoupper($handler['encryption']) : false,
			'user' => $handler['username'],
			'password' => $handler['password'],
		];

		if (!empty($handler['oauth']))
		{
			/** @var array|OAuthImap $protocol */
			$protocol = new OAuthImap($config['host'], $config['port'], $config['ssl']);
		}
		else
		{
			$protocol = new \Laminas\Mail\Protocol\Imap($config['host'], $config['port'], $config['ssl']);
		}

		$authenticated = $protocol->login($config['user'], $config['password']);
		if (!$authenticated)
		{
			throw new RuntimeException('cannot login, user or password wrong');
		}

		return new self($protocol);
	}
}
