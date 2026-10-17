<?php

namespace XF\Repository;

use XF\EditHistory\AbstractHandler;
use XF\Entity\EditHistory;
use XF\Entity\User;
use XF\Finder\EditHistoryFinder;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class EditHistoryRepository extends Repository
{
	/**
	 * @param $contentType
	 * @param null $contentId
	 *
	 * @return Finder
	 */
	public function findEditHistoryForContent($contentType, $contentId = null)
	{
		$finder = $this->finder(EditHistoryFinder::class)
			->where('content_type', $contentType)
			->order('edit_date', 'DESC');

		if ($contentId)
		{
			$finder->where('content_id', $contentId);
		}

		return $finder;
	}

	/**
	 * @param $userId
	 *
	 * @return Finder
	 */
	public function findEditHistoryByUser($userId)
	{
		if ($userId instanceof User)
		{
			$user = $userId;
			$userId = $user->user_id;
		}
		return $this->finder(EditHistoryFinder::class)->where('edit_user_id', $userId)->order('edit_date', 'DESC');
	}

	public function pruneEditHistory($cutOff = null)
	{
		if ($cutOff === null)
		{
			$options = $this->options();
			if ($options->editHistory['enabled'])
			{
				$logLength = $options->editHistory['length'];
				if (!$logLength)
				{
					return 0;
				}
			}
			else
			{
				$logLength = 0;
			}

			$cutOff = \XF::$time - 86400 * $logLength;
		}

		return $this->db()->delete('xf_edit_history', 'edit_date < ?', $cutOff);
	}

	public function revertToHistory(EditHistory $history, ?Entity $content = null, ?AbstractHandler $handler = null)
	{
		$handler = $handler ?: $this->getEditHistoryHandler($history->content_type);
		$content = $content ?: $handler->getContent($history->content_id);

		if (!$content)
		{
			// still couldn't get the content, run away
			return false;
		}

		$historyFinder = $this->finder(EditHistoryFinder::class);

		$histories = $historyFinder->where('content_type', $history->content_type)
			->where('content_id', $history->content_id)
			->order('edit_date', 'DESC');

		$previous = null;
		$useNext = false;
		$count = 0;

		foreach ($histories->fetch() AS $h)
		{
			if ($h->edit_history_id == $history->edit_history_id)
			{
				$useNext = true;
			}
			else if ($useNext)
			{
				$previous = $h;
				break;
			}

			$count++;
		}

		if ($count && $handler->revertToVersion($content, $history, $previous))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function insertEditHistory($contentType, $contentId, User $editUser, $oldText, $logIp = null)
	{
		if ($contentId instanceof Entity)
		{
			$contentId = $contentId->getEntityId();
		}

		/** @var EditHistory $editHistory */
		$editHistory = $this->em->create(EditHistory::class);
		$editHistory->bulkSet([
			'content_type' => $contentType,
			'content_id' => $contentId,
			'edit_user_id' => $editUser->user_id,
			'old_text' => $oldText,
		]);
		$editHistory->save(true, false);

		if ($logIp)
		{
			/** @var IpRepository $ipRepo */
			$ipRepo = $this->repository(IpRepository::class);
			$ipRepo->logIp($editHistory->edit_user_id, $logIp, 'edit_history', $editHistory->edit_history_id, 'insert');
		}

		return $editHistory;
	}

	/**
	 * @param string $type
	 * @param bool $throw
	 *
	 * @return AbstractHandler|null
	 */
	public function getEditHistoryHandler($type, $throw = false)
	{
		$handlerClass = $this->app()->getContentTypeFieldValue($type, 'edit_history_handler_class');
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("No edit history handler for '$type'");
			}
			return null;
		}

		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Edit history handler for '$type' does not exist: $handlerClass");
			}
			return null;
		}

		$handlerClass = \XF::extendClass($handlerClass);
		return new $handlerClass($type, $this->app());
	}
}
