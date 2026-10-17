<?php

namespace XF\Stats\Grouper;

use XF\Language;

abstract class AbstractGrouper
{
	/**
	 * @var Language
	 */
	protected $language;

	public function __construct(Language $language)
	{
		$this->setLanguage($language);
	}

	abstract public function getGrouping($timestamp);
	abstract public function getLabel($groupValue, $timestamp);
	abstract public function getDefaultStartDate();

	public function getTotalTooltip($timestamp, $type, $value)
	{
		return null;
	}

	public function getAverageTooltip($timestamp, $type, $value)
	{
		return null;
	}

	public function setLanguage(Language $language)
	{
		$language = clone $language;
		$language->setTimeZone('UTC');
		$this->language = $language;
	}

	public function getGroupingsInRange($firstTs, $lastTs)
	{
		$output = [];

		$date = new \DateTime("@$firstTs", new \DateTimeZone('UTC'));

		do
		{
			$ts = $date->getTimestamp();
			$grouping = $this->getGrouping($ts);

			if (!isset($output[$grouping]))
			{
				$output[$grouping] = [
					'ts' => $ts,
					'label' => $this->getLabel($grouping, $ts),
					'days' => 1,
				];
			}
			else
			{
				$output[$grouping]['days']++;
			}

			$date->modify('+1 day');
		}
		while ($date->getTimestamp() <= $lastTs);

		return $output;
	}
}
