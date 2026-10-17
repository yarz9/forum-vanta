<?php

namespace XF\Repository;

use XF\Entity\Warning;
use XF\Finder\WarningActionFinder;
use XF\Finder\WarningActionTriggerFinder;
use XF\Finder\WarningDefinitionFinder;
use XF\Finder\WarningFinder;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Warning\AbstractHandler;

use function intval;

class WarningRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findWarningDefinitionsForList()
	{
		return $this->finder(WarningDefinitionFinder::class)->setDefaultOrder('points_default');
	}

	/**
	 * @return Finder
	 */
	public function findWarningActionsForList()
	{
		return $this->finder(WarningActionFinder::class)->setDefaultOrder('points');
	}

	/**
	 * @return Finder
	 */
	public function findUserWarningsForList($userId)
	{
		return $this->finder(WarningFinder::class)
			->where('user_id', $userId)
			->with('WarnedBy')
			->setDefaultOrder('warning_date', 'DESC');
	}

	public function processExpiredWarnings()
	{
		/** @var Warning[] $warnings */
		$warnings = $this->finder(WarningFinder::class)
			->where('expiry_date', '<=', \XF::$time)
			->where('expiry_date', '>', 0)
			->where('is_expired', 0)
			->fetch();
		foreach ($warnings AS $warning)
		{
			$warning->is_expired = true;
			$warning->setOption('log_moderator', false);
			$warning->save();
		}
	}

	/**
	 * @return AbstractHandler[]
	 */
	public function getWarningHandlers()
	{
		$handlers = [];

		foreach (\XF::app()->getContentTypeField('warning_handler_class') AS $contentType => $handlerClass)
		{
			if (class_exists($handlerClass))
			{
				$handlerClass = \XF::extendClass($handlerClass);
				$handlers[$contentType] = new $handlerClass($contentType);
			}
		}

		return $handlers;
	}

	/**
	 * @param string $type
	 * @param bool $throw
	 *
	 * @return AbstractHandler|null
	 */
	public function getWarningHandler($type, $throw = false)
	{
		$handlerClass = \XF::app()->getContentTypeFieldValue($type, 'warning_handler_class');
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("No warning handler for '$type'");
			}
			return null;
		}

		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Warning handler for '$type' does not exist: $handlerClass");
			}
			return null;
		}

		$handlerClass = \XF::extendClass($handlerClass);
		return new $handlerClass($type);
	}

	public function getMinimumUnbanDate($userId)
	{
		$minPoints = null;
		$minUnbanDate = 0;

		$triggers = $this->finder(WarningActionTriggerFinder::class)
			->where('user_id', $userId)
			->order('trigger_points')
			->fetch();

		foreach ($triggers AS $trigger)
		{
			if ($trigger->action == 'ban')
			{
				$minPoints = $trigger->trigger_points;
				$minUnbanDate = $trigger->min_unban_date;
				break;
			}
		}

		if (!$minPoints)
		{
			return null;
		}

		$totalPoints = 0;
		$expiry = [];
		$points = [];
		foreach ($this->findUserWarningsForList($userId)->fetch() AS $warning)
		{
			if ($warning->is_expired || !$warning->points)
			{
				continue;
			}

			if ($warning->expiry_date)
			{
				$expiry[] = $warning->expiry_date;
				$points[] = $warning->points;
			}

			$totalPoints += $warning->points;
		}

		if ($totalPoints < $minPoints)
		{
			return null;
		}

		asort($expiry);
		foreach ($expiry AS $key => $expiryDate)
		{
			$totalPoints -= $points[$key];
			if ($totalPoints < $minPoints)
			{
				return max($minUnbanDate, $expiryDate);
			}
		}

		return null;
	}

	public function getActiveWarningPointsForUser($userId)
	{
		return intval($this->db()->fetchOne("
			SELECT SUM(points)
			FROM xf_warning
			WHERE user_id = ?
				AND is_expired = 0
		", $userId));
	}
}
