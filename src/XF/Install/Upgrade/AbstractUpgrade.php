<?php

namespace XF\Install\Upgrade;

use XF\App;
use XF\Install\InstallHelperTrait;

use function strlen;

abstract class AbstractUpgrade
{
	use InstallHelperTrait;

	abstract public function getVersionName();

	public function __construct(App $app)
	{
		$this->app = $app;
	}

	public function insertUpgradeJob($uniqueKey, $jobClass, array $params = [], $immediate = true)
	{
		if (strlen($uniqueKey) > 50)
		{
			$uniqueKey = md5($uniqueKey);
		}

		$this->db()->insert('xf_upgrade_job', [
			'unique_key' => $uniqueKey,
			'execute_class' => $jobClass,
			'execute_data' => serialize($params),
			'immediate' => $immediate ? 1 : 0,
		], false, '
			execute_class = VALUES(execute_class),
			execute_data = VALUES(execute_data),
			immediate = VALUES(immediate)
		');

		return $uniqueKey;
	}

	public function insertPostUpgradeJob($uniqueKey, $jobClass, array $params = [])
	{
		return $this->insertUpgradeJob($uniqueKey, $jobClass, $params, false);
	}

	public function repromptStatsCollectionOptIn($reasons = [])
	{
		$session = $this->app->session();

		$session->repromptStatsCollection = true;

		$_reasons = $session->repromptStatsCollectionReasons ?: [];
		foreach ((array) $reasons AS $reason)
		{
			$_reasons[] = $reason;
		}

		$session->repromptStatsCollectionReasons = $_reasons;
	}

	public function insertNewOptionInitialValue(array $values = [], bool $replaceInto = false, string $modifier = '')
	{
		$values = array_replace([
			'option_id' => null,
			'option_value' => '',
			'default_value' => '',
			'edit_format' => 'textbox',
			'edit_format_params' => '',
			'data_type' => 'string',
			'sub_options' => '',
			'validation_class' => '',
			'validation_method' => '',
			'advanced' => 0,
			'addon_id' => 'XF',
		], $values);

		if (!$values['option_id'])
		{
			return;
		}

		$this->db()->insert('xf_option', $values, $replaceInto, false, $modifier);
	}
}
