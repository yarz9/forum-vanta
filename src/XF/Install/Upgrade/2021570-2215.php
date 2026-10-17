<?php

namespace XF\Install\Upgrade;

class Version2021570 extends AbstractUpgrade
{
	public function getVersionName()
	{
		return '2.2.15';
	}

	public function step1()
	{
		$unsubEmailAddress = \XF::options()->unsubscribeEmailAddress ?? null;

		$unsubEmailValue = \XF::db()->fetchOne('
			SELECT option_value
			FROM xf_option
			WHERE option_id = ?
		', 'unsubscribeEmail');

		if ($unsubEmailValue)
		{
			$unsubEmailValue = json_decode($unsubEmailValue, true);
		}
		else
		{
			$unsubEmailValue = [
				'unsubscribe_type' => ['http'],
				'unsubscribe_email_address' => '',
			];
		}

		$unsubEmailNew = $unsubEmailAddress ?? $unsubEmailValue['unsubscribe_email_address'] ?? '';

		if (!isset($unsubEmailValue['unsubscribe_type']))
		{
			$unsubEmailValue['unsubscribe_type'] = ['http'];
		}

		if (in_array('html', $unsubEmailValue['unsubscribe_type']))
		{
			$unsubscribeType = ['http'];
			if (in_array('email', $unsubEmailValue['unsubscribe_type']))
			{
				$unsubscribeType[] = 'email';
			}

			$unsubEmailValue['unsubscribe_type'] = $unsubscribeType;
		}

		$newOptionValue = [
			'http' => false,
			'email' => false,
		];
		$newOptionDefault = ['http' => true, 'email' => false];

		if (in_array('http', $unsubEmailValue['unsubscribe_type']))
		{
			$newOptionValue['http'] = true;
		}
		if (in_array('email', $unsubEmailValue['unsubscribe_type']))
		{
			$newOptionValue['email'] = true;
		}

		$this->insertNewOptionInitialValue([
			'option_id' => 'unsubscribeEmailHandling',
			'option_value' => json_encode($newOptionValue),
			'default_value' => json_encode($newOptionDefault),
			'edit_format' => 'checkbox',
			'data_type' => 'array',
			'sub_options' => "http\nemail",
		], true);

		$this->insertNewOptionInitialValue([
			'option_id' => 'unsubscribeEmailAddress',
			'option_value' => $unsubEmailNew,
		], false, 'IGNORE');
	}
}
