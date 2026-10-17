<?php

namespace XF\Pub\Route;

use XF\Entity\User;

class EmailStop
{
	public static function build(&$prefix, array &$route, &$action, &$data, array &$params)
	{
		if ($data instanceof User)
		{
			$params['c'] = $data->email_confirm_key;
		}

		return null; // default processing otherwise
	}
}
