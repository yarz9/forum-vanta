<?php

namespace XF\Legacy;

use XF\Db\AbstractAdapter;
use XF\Options;
use XF\Session\Session;

class Application
{
	public static function debugMode()
	{
		return \XF::$debugMode;
	}

	public static function get($key)
	{
		switch ($key)
		{
			case 'options': return \XF::options();
			case 'config': return \XF::config();
			case 'db': return \XF::db();
			case 'session': return \XF::session();

			default:
				throw new \InvalidArgumentException("Can't load '$key'");
		}
	}

	/**
	 * @return AbstractAdapter
	 */
	public static function getDb()
	{
		return self::get('db');
	}

	/**
	 * @return Session
	 */
	public static function getSession()
	{
		return self::get('session');
	}

	/**
	 * @return array
	 */
	public static function getConfig()
	{
		return self::get('config');
	}

	/**
	 * @return Options
	 */
	public static function getOptions()
	{
		return self::get('options');
	}
}
