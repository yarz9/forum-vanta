<?php

namespace XF\Repository;

use XF\Entity\Notice;
use XF\Entity\User;
use XF\Finder\NoticeFinder;
use XF\Finder\TemplateFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Util\Php;

use function count, in_array;

class NoticeRepository extends Repository
{
	/**
	 * @return Finder
	 */
	public function findNoticesForList()
	{
		return $this->finder(NoticeFinder::class)->order(['display_order']);
	}

	/**
	 * Checks through notices to see if any have page criteria which may no longer being valid.
	 * This could be templates, views or controllers from old XF versions or uninstalled add-ons.
	 *
	 * @param AbstractCollection|Notice[] $notices
	 * @return array
	 */
	public function getInvalidNotices(AbstractCollection $notices)
	{
		$invalidNotices = [];

		foreach ($notices AS $notice)
		{
			if (!$notice->active || !$notice->page_criteria)
			{
				continue;
			}

			if (!$this->isPageCriteriaValid($notice->page_criteria))
			{
				$invalidNotices[] = $notice;
			}
		}

		return $invalidNotices;
	}

	protected function isPageCriteriaValid(array $criteria)
	{
		$templatesCache = $this->getTemplatesForCriteriaCheck();

		foreach ($criteria AS $criterion)
		{
			switch ($criterion['rule'])
			{
				case 'template':
					if (isset($criterion['data']['name']))
					{
						return in_array($criterion['data']['name'], $templatesCache);
					}
					break;

				case 'view':
					if (isset($criterion['data']['name']))
					{
						return class_exists($criterion['data']['name']);
					}
					break;

				case 'controller':
					if (isset($criterion['data']['name']))
					{
						try
						{
							if (!empty($criterion['data']['action']))
							{
								$class = \XF::extendClass($criterion['data']['name']);
								$method = 'action' . Php::camelCase($criterion['data']['action'], '-');
								return method_exists($class, $method);
							}
							else
							{
								return class_exists($criterion['data']['name']);
							}
						}
						catch (\Throwable $e)
						{
							return false;
						}
					}
					break;
			}
		}

		return true;
	}

	protected $templatesCache;

	protected function getTemplatesForCriteriaCheck()
	{
		if ($this->templatesCache === null)
		{
			$this->templatesCache = $this->finder(TemplateFinder::class)
				->where('type', 'public')
				->where('style_id', 0)
				->order('title')
				->fetch()->pluckNamed('title');
		}

		return $this->templatesCache;
	}

	public function getNoticeTypes()
	{
		return [
			'block' => \XF::phrase('block'),
			'scrolling' => \XF::phrase('scrolling'),
			'floating' => \XF::phrase('floating'),
			'bottom_fixer' => \XF::phrase('fixed'),
		];
	}

	public function getDismissedNoticesForUser(User $user)
	{
		return $this->db()->fetchAllKeyed('
			SELECT *
			FROM xf_notice_dismissed
			WHERE user_id = ?
		', 'notice_id', $user->user_id);
	}

	public function dismissNotice(Notice $notice, User $user)
	{
		$fields = [
			'notice_id' => $notice->notice_id,
			'user_id' => $user->user_id,
			'dismiss_date' => time(),
		];
		return $this->db()->insert(
			'xf_notice_dismissed',
			$fields,
			false,
			false,
			'IGNORE'
		);
	}

	public function restoreDismissedNotices(User $user)
	{
		return $this->db()->delete('xf_notice_dismissed', 'user_id = ?', $user->user_id);
	}

	public function resetNoticeDismissal(Notice $notice)
	{
		$this->db()->delete('xf_notice_dismissed', 'notice_id = ?', $notice->notice_id);
		\XF::registry()->set('noticesLastReset', time());
	}

	public function rebuildNoticeCache()
	{
		$cache = [];

		$notices = $this->finder(NoticeFinder::class)
			->where('active', 1)
			->order('display_order')
			->keyedBy('notice_id');

		foreach ($notices->fetch() AS $noticeId => $notice)
		{
			$cache[$noticeId] = $notice->toArray(false);
		}

		\XF::registry()->set('notices', $cache);
		return $cache;
	}

	public function rebuildNoticeLastResetCache()
	{
		\XF::registry()->set('noticesLastReset', 0);

		return 0;
	}

	public function getTotalGroupedNotices(array $groupedNotices)
	{
		$total = 0;

		foreach ($groupedNotices AS $notices)
		{
			$total += count($notices);
		}

		return $total;
	}
}
