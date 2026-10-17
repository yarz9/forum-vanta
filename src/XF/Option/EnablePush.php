<?php

namespace XF\Option;

use Minishlink\WebPush\VAPID;
use XF\Entity\Option;
use XF\Repository\OptionRepository;

class EnablePush extends AbstractOption
{
	protected static function canEnablePush(&$error = null)
	{
		$extensions = [
			'gmp',
			'mbstring',
			'openssl',
		];

		$missing = [];
		foreach ($extensions AS $extension)
		{
			if (!extension_loaded($extension))
			{
				$missing[] = $extension;
			}
		}

		if ($missing)
		{
			$error = \XF::phrase('enabling_push_notifications_requires_php_to_have_following_extensions', [
				'extensions' => implode(', ', $missing),
			]);
			return false;
		}

		$request = \XF::app()->request();

		if (!$request->isHostLocal() && !$request->isSecure() && PHP_SAPI != 'cli')
		{
			$error = \XF::phrase('enabling_push_notifications_requires_site_to_be_accessible_over_https');
			return false;
		}

		return true;
	}

	public static function renderOption(Option $option, array $htmlParams)
	{
		$canEnablePush = static::canEnablePush($error);

		return static::getTemplate('admin:option_template_enablePush', $option, $htmlParams, [
			'canEnablePush' => $canEnablePush,
			'error' => $error,
		]);
	}

	public static function verifyOption(&$value, Option $option)
	{
		if ($option->isInsert())
		{
			return true;
		}

		$canEnablePush = static::canEnablePush($error);

		if ($value === 1 && !$canEnablePush)
		{
			$option->error($error, $option->option_id);
			return false;
		}

		$options = \XF::options();

		if ($value === 1
			&& !$options->pushKeysVAPID['publicKey']
			&& !$options->pushKeysVAPID['privateKey']
		)
		{
			/** @var OptionRepository $optionRepo */
			$optionRepo = \XF::repository(OptionRepository::class);

			$optionRepo->updateOptionSkipVerify(
				'pushKeysVAPID',
				VAPID::createVapidKeys()
			);
		}

		return true;
	}

	public static function verifyVapidKeysOption(&$value, Option $option)
	{
		if ($option->isInsert())
		{
			return true;
		}

		if ($option->option_value['publicKey'] || $option->option_value['privateKey'])
		{
			$changes = false;

			if ($value['publicKey'] !== $option->option_value['publicKey'])
			{
				$changes = true;
			}
			if ($value['privateKey'] !== $option->option_value['privateKey'])
			{
				$changes = true;
			}

			if ($changes)
			{
				$option->error(\XF::phrase('it_is_not_possible_to_change_vapid_keys_after_they_have_been_set'), $option->option_id);
				return false;
			}
		}

		return true;
	}
}
