<?php

namespace XF\Repository;

use XF\Alert\AbstractHandler;
use XF\Db\AbstractAdapter;
use XF\Db\DeadlockException;
use XF\Entity\User;
use XF\Entity\UserAlert;
use XF\Finder\UserAlertFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;
use XF\Service\Alert\PusherService;

use function is_array;

class UserAlertRepository extends Repository
{
	/**
	 * @param int $userId
	 * @param null|int $cutOff
	 *
	 * @return UserAlertFinder
	 */
	public function findAlertsForUser($userId, $cutOff = null)
	{
		$finder = $this->finder(UserAlertFinder::class)
			->where('alerted_user_id', $userId)
			->whereAddOnActive([
				'column' => 'depends_on_addon_id',
			])
			->order('event_date', 'desc')
			->with('User');

		if ($cutOff)
		{
			$finder->whereOr(
				[
					['read_date', '=', 0],
					['view_date', '=', 0],
					['view_date', '>=', $cutOff],
				]
			);
		}

		return $finder;
	}

	/**
	 * @param int $senderId
	 * @param string $contentType
	 * @param string $action
	 *
	 * @return bool
	 */
	public function userReceivesAlert(User $receiver, $senderId, $contentType, $action)
	{
		if (!$receiver->user_id)
		{
			return false;
		}

		if ($senderId && $receiver->isIgnoring($senderId))
		{
			return false;
		}

		if (!$receiver->Option)
		{
			return true;
		}

		$userOption = $receiver->Option;
		return $userOption->doesReceiveAlert($contentType, $action);
	}

	/**
	 * @param int $senderId
	 * @param string $contentType
	 * @param string $action
	 *
	 * @return bool
	 */
	public function userReceivesPush(User $receiver, $senderId, $contentType, $action)
	{
		if (!$receiver->user_id || $receiver->is_banned)
		{
			return false;
		}

		if ($senderId && $receiver->isIgnoring($senderId))
		{
			return false;
		}

		if (!$receiver->Option)
		{
			return true;
		}

		$userOption = $receiver->Option;
		return $userOption->doesReceivePush($contentType, $action);
	}

	/**
	 * @param string $contentType
	 * @param int $contentId
	 * @param string $action
	 * @param array<string, mixed> $extra
	 * @param array<string, mixed> $options
	 *
	 * @return bool
	 */
	public function alertFromUser(
		User $receiver,
		?User $sender,
		$contentType,
		$contentId,
		$action,
		array $extra = [],
		array $options = []
	)
	{
		$senderId = $sender ? $sender->user_id : 0;
		$senderName = $sender ? $sender->username : '';

		if (!$this->userReceivesAlert($receiver, $senderId, $contentType, $action))
		{
			return false;
		}

		return $this->insertAlert($receiver->user_id, $senderId, $senderName, $contentType, $contentId, $action, $extra, $options);
	}

	/**
	 * @param int $senderId
	 * @param string $senderName
	 * @param string $contentType
	 * @param int $contentId
	 * @param string $action
	 * @param array<string, mixed> $extra
	 * @param array<string, mixed> $options
	 *
	 * @return bool
	 */
	public function alert(
		User $receiver,
		$senderId,
		$senderName,
		$contentType,
		$contentId,
		$action,
		array $extra = [],
		array $options = []
	)
	{
		if (!$this->userReceivesAlert($receiver, $senderId, $contentType, $action))
		{
			return false;
		}

		return $this->insertAlert($receiver->user_id, $senderId, $senderName, $contentType, $contentId, $action, $extra, $options);
	}

	/**
	 * @param int $receiverId
	 * @param int $senderId
	 * @param string $senderName
	 * @param string $contentType
	 * @param int $contentId
	 * @param string $action
	 * @param array<string, mixed> $extra
	 * @param array<string, mixed> $options
	 *
	 * @return bool
	 */
	public function insertAlert(
		$receiverId,
		$senderId,
		$senderName,
		$contentType,
		$contentId,
		$action,
		array $extra = [],
		array $options = []
	)
	{
		if (!$receiverId)
		{
			return false;
		}

		$options = array_replace([
			'autoRead' => true,
			'dependsOnAddOnId' => null,
		], $options);

		if ($options['dependsOnAddOnId'] === null)
		{
			if (isset($extra['depends_on_addon_id']))
			{
				$options['dependsOnAddOnId'] = $extra['depends_on_addon_id'];
				unset($extra['depends_on_addon_id']);
			}
			else
			{
				$options['dependsOnAddOnId'] = '';
			}
		}

		$alert = $this->em->create(UserAlert::class);
		$alert->alerted_user_id = $receiverId;
		$alert->user_id = $senderId;
		$alert->username = $senderName;
		$alert->content_type = $contentType;
		$alert->content_id = $contentId;
		$alert->action = $action;
		$alert->extra_data = $extra;
		$alert->depends_on_addon_id = $options['dependsOnAddOnId'];
		$alert->auto_read = (bool) $options['autoRead'];

		$alert->save();

		if ($alert->Receiver && $this->userReceivesPush($alert->Receiver, $senderId, $contentType, $action))
		{
			$pusher = $this->app()->service(PusherService::class, $alert->Receiver, $alert);
			$pusher->push();
		}

		return true;
	}

	/**
	 * @param int $toUserId
	 * @param string $contentType
	 * @param int $contentId
	 * @param string $action
	 */
	public function fastDeleteAlertsToUser($toUserId, $contentType, $contentId, $action)
	{
		$finder = $this->finder(UserAlertFinder::class)
			->where([
				'content_type' => $contentType,
				'content_id' => $contentId,
				'action' => $action,
				'alerted_user_id' => $toUserId,
			]);

		$this->deleteAlertsInternal($finder);
		// TODO: approach will need to change if there's alert folding
	}

	/**
	 * @param int $fromUserId
	 * @param string $contentType
	 * @param int $contentId
	 * @param string $action
	 */
	public function fastDeleteAlertsFromUser($fromUserId, $contentType, $contentId, $action)
	{
		$finder = $this->finder(UserAlertFinder::class)
			->where([
				'content_type' => $contentType,
				'content_id' => $contentId,
				'action' => $action,
				'user_id' => $fromUserId,
			]);

		$this->deleteAlertsInternal($finder);
		// TODO: approach will need to change if there's alert folding
	}

	/**
	 * @param string $contentType
	 * @param int $contentId
	 */
	public function fastDeleteAlertsForContent($contentType, $contentId)
	{
		$finder = $this->finder(UserAlertFinder::class)
			->where([
				'content_type' => $contentType,
				'content_id' => $contentId,
			]);

		$this->deleteAlertsInternal($finder);
	}

	/**
	 * @param UserAlertFinder $matches
	 */
	protected function deleteAlertsInternal(Finder $matches)
	{
		$results = $matches->fetchColumns('alert_id', 'alerted_user_id', 'view_date', 'read_date');
		if (!$results)
		{
			return;
		}

		$userIds = [];
		$viewCountChange = [];
		$readCountChange = [];
		$delete = [];

		foreach ($results AS $result)
		{
			$delete[] = $result['alert_id'];

			$userIds[$result['alerted_user_id']] = $result['alerted_user_id'];

			if (!$result['view_date'])
			{
				if (isset($viewCountChange[$result['alerted_user_id']]))
				{
					$viewCountChange[$result['alerted_user_id']]++;
				}
				else
				{
					$viewCountChange[$result['alerted_user_id']] = 1;
				}
			}

			if (!$result['read_date'])
			{
				if (isset($readCountChange[$result['alerted_user_id']]))
				{
					$readCountChange[$result['alerted_user_id']]++;
				}
				else
				{
					$readCountChange[$result['alerted_user_id']] = 1;
				}
			}
		}

		$db = $this->db();
		$db->beginTransaction();

		$db->delete('xf_user_alert', 'alert_id IN (' . $db->quote($delete) . ')');

		foreach ($userIds AS $userId)
		{
			$viewChange = $viewCountChange[$userId] ?? 0;
			$readChange = $readCountChange[$userId] ?? 0;

			$db->query("
				UPDATE xf_user
				SET alerts_unviewed = GREATEST(0, CAST(alerts_unviewed AS SIGNED) - ?),
					alerts_unread = GREATEST(0, CAST(alerts_unread AS SIGNED) - ?)
				WHERE user_id = ?
			", [$viewChange, $readChange, $userId]);
		}

		$db->commit();
	}

	/**
	 * @param int|null $viewDate
	 */
	public function markUserAlertsViewed(User $user, $viewDate = null)
	{
		$this->markAllUserAlertsViewedOrRead($user, $viewDate);
	}

	/**
	 * @param int|null $viewDate
	 */
	public function markUserAlertViewed(UserAlert $alert, $viewDate = null)
	{
		if (!$alert->Receiver || !$alert->isUnviewed())
		{
			return;
		}

		$this->markUserAlertsViewedOrRead(
			$alert->Receiver,
			[$alert->alert_id],
			$viewDate
		);
	}

	/**
	 * @param int|null $readDate
	 */
	public function markUserAlertsRead(User $user, $readDate = null)
	{
		$this->markAllUserAlertsViewedOrRead($user, $readDate, true);
	}

	/**
	 * @param AbstractCollection<UserAlert> $alerts
	 * @param int|null $readDate
	 */
	public function autoMarkUserAlertsRead(
		AbstractCollection $alerts,
		User $user,
		$readDate = null
	)
	{
		$alerts = $alerts->filter(function (UserAlert $alert): bool
		{
			return ($alert->isUnread() && $alert->auto_read);
		});

		$this->markSpecificUserAlertsRead($alerts, $user, $readDate);
	}

	/**
	 * @param AbstractCollection<UserAlert> $alerts
	 */
	protected function markSpecificUserAlertsRead(
		AbstractCollection $alerts,
		User $user,
		?int $readDate = null
	)
	{
		if (!$user->user_id)
		{
			throw new \LogicException(
				'Trying to mark alerts read for an invalid user'
			);
		}

		$unreadAlertIds = [];
		foreach ($alerts AS $alert)
		{
			if (!$alert->isUnread())
			{
				continue;
			}

			$unreadAlertIds[] = $alert->alert_id;
			// need to force unread for this request so it can display properly
			$alert->setOption('force_unread_in_ui', true);
		}

		$this->markUserAlertsViewedOrRead(
			$user,
			$unreadAlertIds,
			$readDate,
			true,
			true
		);
	}

	/**
	 * @param string $contentType
	 * @param list<int>|int $contentIds
	 * @param list<string>|string|null $onlyActions
	 * @param User|null $user
	 * @param int|null $readDate
	 */
	public function markUserAlertsReadForContent(
		$contentType,
		$contentIds,
		$onlyActions = null,
		?User $user = null,
		$readDate = null
	)
	{
		if (!is_array($contentIds))
		{
			$contentIds = [$contentIds];
		}

		if (!$contentIds)
		{
			return;
		}

		$user = $user ?? \XF::visitor();
		if (!$user->user_id || !$user->alerts_unread)
		{
			return;
		}

		$readDate = $readDate ?? \XF::$time;

		$db = $this->db();

		$actionsClause = '';
		if ($onlyActions)
		{
			if (!is_array($onlyActions))
			{
				$onlyActions = [$onlyActions];
			}

			$actionsClause = ' AND action IN (' . $db->quote($onlyActions) . ')';
		}

		$unreadAlertIds = $db->fetchAllColumn(
			"SELECT alert_id
				FROM xf_user_alert
				WHERE alerted_user_id = ?
				    AND content_type = ?
					AND content_id IN (" . $db->quote($contentIds) . ")
					AND event_date < ?
					AND read_date = 0
					{$actionsClause}",
			[$user->user_id, $contentType, $readDate]
		);

		$this->markUserAlertsViewedOrRead(
			$user,
			$unreadAlertIds,
			$readDate,
			true
		);
	}

	/**
	 * @param int|null $readDate
	 */
	public function markUserAlertRead(UserAlert $alert, $readDate = null)
	{
		if (!$alert->Receiver || !$alert->isUnread())
		{
			return;
		}

		$this->markUserAlertsViewedOrRead(
			$alert->Receiver,
			[$alert->alert_id],
			$readDate,
			true
		);
	}

	public function markUserAlertUnread(
		UserAlert $alert,
		bool $disableAutoRead = true
	)
	{
		if (!$alert->Receiver || $alert->isUnread())
		{
			return;
		}

		$this->markUserAlertsUnread(
			$alert->Receiver,
			[$alert->alert_id],
			$disableAutoRead
		);
	}

	/**
	 * Makes alerts that aren't accessible as read. This is primarily to prevent
	 * unread alerts being "stuck". Alerts meet this criteria if they depend on
	 * a disabled add-on, don't have a valid handler or the related content is
	 * not viewable.
	 */
	public function markInaccessibleAlertsRead(User $user)
	{
		$unreadAlerts = $this->finder(UserAlertFinder::class)
			->where([
				'alerted_user_id' => $user->user_id,
				'read_date' => 0,
			])
			->fetch();
		$this->addContentToAlerts($unreadAlerts);

		$addOns = \XF::app()->container('addon.cache');
		$invalidAlerts = $unreadAlerts->filter(function (UserAlert $alert) use ($addOns): bool
		{
			if (
				$alert->depends_on_addon_id &&
				!isset($addOns[$alert->depends_on_addon_id])
			)
			{
				return true;
			}

			return !$alert->canView();
		});

		$this->markSpecificUserAlertsRead($invalidAlerts, $user);
	}

	protected function markAllUserAlertsViewedOrRead(
		User $user,
		?int $viewDate = null,
		bool $markRead = false
	): void
	{
		if ($this->app()->request()->isPrefetch())
		{
			return;
		}

		if (!$user->user_id)
		{
			throw new \LogicException(
				'Trying to mark alerts viewed for an invalid user'
			);
		}

		$viewDate = $viewDate ?? \XF::$time;

		$db = $this->db();

		$db->executeTransaction(
			function () use ($db, $user, $viewDate, $markRead): void
			{
				$userUpdates = ['alerts_unviewed' => 0];
				if ($markRead)
				{
					$userUpdates['alerts_unread'] = 0;
				}

				$alertUpdates = ['view_date' => $viewDate];
				if ($markRead)
				{
					$alertUpdates['read_date'] = $viewDate;
				}

				$alertClause = 'view_date = 0';
				if ($markRead)
				{
					$alertClause .= ' OR read_date = 0';
				}

				$db->update(
					'xf_user',
					$userUpdates,
					'user_id = ?',
					[$user->user_id]
				);

				$db->update(
					'xf_user_alert',
					$alertUpdates,
					"alerted_user_id = ? AND ({$alertClause})",
					[$user->user_id]
				);
			},
			AbstractAdapter::ALLOW_DEADLOCK_RERUN
		);

		$user->setAsSaved('alerts_unviewed', 0);
		if ($markRead)
		{
			$user->setAsSaved('alerts_unread', 0);
		}
	}

	/**
	 * @param list<int> $alertIds
	 */
	protected function markUserAlertsViewedOrRead(
		User $user,
		array $alertIds,
		?int $viewDate = null,
		bool $markRead = false,
		bool $updateAlertEntities = false
	): void
	{
		if ($this->app()->request()->isPrefetch())
		{
			return;
		}

		if (!$alertIds)
		{
			return;
		}

		$viewDate = $viewDate ?? \XF::$time;

		$db = $this->db();

		if ($db->inTransaction())
		{
			$db->query(
				'SELECT user_id
					FROM xf_user
					WHERE user_id = ? FOR UPDATE',
				[$user->user_id]
			);
		}

		$viewedCount = $db->update(
			'xf_user_alert',
			['view_date' => $viewDate],
			'alert_id IN (' . $db->quote($alertIds) . ') AND view_date = 0'
		);

		$readCount = 0;
		if ($markRead)
		{
			$readCount = $db->update(
				'xf_user_alert',
				['read_date' => $viewDate],
				'alert_id IN (' . $db->quote($alertIds) . ') AND read_date = 0'
			);
		}

		if (!$viewedCount && !$readCount)
		{
			return;
		}

		$updateUserCounters = function () use ($db, $viewedCount, $readCount, $user): int
		{
			$statement = $db->query(
				'UPDATE xf_user
					SET alerts_unviewed = GREATEST(0, CAST(alerts_unviewed AS SIGNED) - ?),
						alerts_unread = GREATEST(0, CAST(alerts_unread AS SIGNED) - ?)
					WHERE user_id = ?',
				[$viewedCount, $readCount, $user->user_id]
			);

			return $statement->rowsAffected();
		};

		try
		{
			$updateUserCounters();

			$user->setAsSaved(
				'alerts_unviewed',
				max(0, $user->alerts_unviewed - $viewedCount)
			);
			$user->setAsSaved(
				'alerts_unread',
				max(0, $user->alerts_unread - $readCount)
			);
		}
		catch (DeadlockException $e)
		{
			$usersUpdated = $updateUserCounters();
			if (!$usersUpdated)
			{
				return;
			}

			$counts = $db->fetchRow(
				'SELECT alerts_unviewed, alerts_unread
					FROM xf_user
					WHERE user_id = ?',
				[$user->user_id]
			);

			$user->setAsSaved('alerts_unviewed', $counts['alerts_unviewed']);
			$user->setAsSaved('alerts_unread', $counts['alerts_unread']);
		}

		if (!$updateAlertEntities)
		{
			return;
		}

		foreach ($alertIds AS $alertId)
		{
			$alert = $this->em->findCached(UserAlert::class, $alertId);
			if (!$alert)
			{
				continue;
			}

			$alert->setAsSaved('view_date', $viewDate);
			if ($markRead)
			{
				$alert->setAsSaved('read_date', $viewDate);
			}
		}
	}

	/**
	 * @param list<int> $alertIds
	 */
	protected function markUserAlertsUnread(
		User $user,
		array $alertIds,
		bool $disableAutoRead = true,
		bool $updateAlertEntities = false
	): void
	{
		if ($this->app()->request()->isPrefetch())
		{
			return;
		}

		if (!$alertIds)
		{
			return;
		}

		$db = $this->db();

		if ($db->inTransaction())
		{
			$db->query(
				'SELECT user_id
					FROM xf_user
					WHERE user_id = ? FOR UPDATE',
				[$user->user_id]
			);
		}

		$alertUpdates = ['read_date' => 0];
		if ($disableAutoRead)
		{
			$alertUpdates['auto_read'] = 0;
		}

		$unreadCount = $db->update(
			'xf_user_alert',
			$alertUpdates,
			'alert_id IN (' . $db->quote($alertIds) . ') AND view_date > 0'
		);
		if (!$unreadCount)
		{
			return;
		}

		$updateUserCounters = function () use ($db, $unreadCount, $user): int
		{
			$statement = $db->query(
				'UPDATE xf_user
					SET alerts_unread = alerts_unread + ?
					WHERE user_id = ?',
				[$unreadCount, $user->user_id]
			);

			return $statement->rowsAffected();
		};

		try
		{
			$updateUserCounters();

			$user->setAsSaved(
				'alerts_unread',
				$user->alerts_unread + $unreadCount
			);
		}
		catch (DeadlockException $e)
		{
			$rowsAffected = $updateUserCounters();
			if (!$rowsAffected)
			{
				return;
			}

			$unreadCount = $db->fetchOne(
				'SELECT alerts_unread
					FROM xf_user
					WHERE user_id = ?',
				[$user->user_id]
			);

			$user->setAsSaved('alerts_unread', $unreadCount);
		}

		if (!$updateAlertEntities)
		{
			return;
		}

		foreach ($alertIds AS $alertId)
		{
			$alert = $this->em->findCached(UserAlert::class, $alertId);
			if (!$alert)
			{
				continue;
			}

			$alert->setAsSaved('read_date', 0);
			if ($disableAutoRead)
			{
				$alert->setAsSaved('auto_read', false);
			}
		}
	}

	/**
	 * @param int|null $cutOff
	 */
	public function pruneViewedAlerts($cutOff = null)
	{
		$cutOff = $cutOff ?? \XF::$time - $this->options()->alertExpiryDays * 86400;

		$finder = $this->finder(UserAlertFinder::class)
			->where('view_date', '>', 0)
			->where('view_date', '<', $cutOff);

		$this->deleteAlertsInternal($finder);
	}

	/**
	 * @param int|null $cutOff
	 */
	public function pruneUnviewedAlerts($cutOff = null)
	{
		$cutOff = $cutOff ?? \XF::$time - 30 * 86400;

		$finder = $this->finder(UserAlertFinder::class)
			->where('view_date', 0)
			->where('event_date', '<', $cutOff);

		$this->deleteAlertsInternal($finder);
	}

	/**
	 * @return bool
	 */
	public function updateUnviewedCountForUser(User $user)
	{
		if (!$user->user_id)
		{
			return false;
		}

		$count = $this->findAlertsForUser($user->user_id)
			->where('view_date', 0)
			->total();

		$user->alerts_unviewed = $count;
		$user->saveIfChanged($updated);

		return $updated;
	}

	/**
	 * @return bool
	 */
	public function updateUnreadCountForUser(User $user)
	{
		if (!$user->user_id)
		{
			return false;
		}

		$count = $this->findAlertsForUser($user->user_id)
			->where('read_date', 0)
			->total();

		$user->alerts_unread = $count;
		$user->saveIfChanged($updated);

		return $updated;
	}

	/**
	 * @return array<string, AbstractHandler>
	 */
	public function getAlertHandlers()
	{
		$handlers = [];

		foreach (\XF::app()->getContentTypeField('alert_handler_class') AS $contentType => $handlerClass)
		{
			if (!class_exists($handlerClass))
			{
				continue;
			}

			$handlerClass = \XF::extendClass($handlerClass);
			$handlers[$contentType] = new $handlerClass($contentType);
		}

		return $handlers;
	}

	/**
	 * @param string $type
	 * @param bool $throw
	 *
	 * @return AbstractHandler|null
	 */
	public function getAlertHandler($type, $throw = false)
	{
		$handlerClass = \XF::app()->getContentTypeFieldValue($type, 'alert_handler_class');
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("No Alert handler for '$type'");
			}

			return null;
		}

		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Alert handler for '$type' does not exist: $handlerClass");
			}

			return null;
		}

		$handlerClass = \XF::extendClass($handlerClass);
		return new $handlerClass($type);
	}

	/**
	 * @param AbstractCollection<UserAlert> $alerts
	 */
	public function addContentToAlerts($alerts)
	{
		$contentMap = [];
		foreach ($alerts AS $key => $alert)
		{
			$contentType = $alert->content_type;
			if (!isset($contentMap[$contentType]))
			{
				$contentMap[$contentType] = [];
			}

			$contentMap[$contentType][$key] = $alert->content_id;
		}

		foreach ($contentMap AS $contentType => $contentIds)
		{
			$handler = $this->getAlertHandler($contentType);
			if (!$handler)
			{
				continue;
			}

			$data = $handler->getContent($contentIds);
			foreach ($contentIds AS $alertId => $contentId)
			{
				$content = $data[$contentId] ?? null;
				$alerts[$alertId]->setContent($content);
			}
		}
	}

	/**
	 * @return array<string, array<string, string|\Stringable>>
	 */
	public function getAlertOptOuts()
	{
		$handlers = $this->getAlertHandlers();

		$alertOptOuts = [];
		$orderedTypes = [];

		foreach ($handlers AS $contentType => $handler)
		{
			$optOuts = $handler->getOptOutsMap();
			if (!$optOuts)
			{
				continue;
			}

			$alertOptOuts[$contentType] = $optOuts;
			$orderedTypes[$contentType] = $handler->getOptOutDisplayOrder();
		}
		asort($orderedTypes);

		$orderedOptOuts = [];
		foreach ($orderedTypes AS $contentType => $null)
		{
			$orderedOptOuts[$contentType] = $alertOptOuts[$contentType];
		}

		return $orderedOptOuts;
	}

	/**
	 * @return array<string, true>
	 */
	public function getAlertOptOutActions()
	{
		$handlers = $this->getAlertHandlers();

		$actions = [];
		foreach ($handlers AS $contentType => $handler)
		{
			foreach ($handler->getOptOutActions() AS $action)
			{
				$actions[$contentType . '_' . $action] = true;
			}
		}

		return $actions;
	}
}
