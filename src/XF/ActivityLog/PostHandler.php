<?php

namespace XF\ActivityLog;

use XF\Entity\Post;
use XF\Mvc\Entity\Entity;

use function in_array;

/**
 * @extends AbstractShimHandler<Post>
 */
class PostHandler extends AbstractShimHandler
{
	public function log(
		Entity $content,
		int $logDate,
		array $values,
		bool $increment
	): void
	{
		if (!$content->isFirstPost())
		{
			return;
		}

		$thread = $content->Thread;
		if (!$thread)
		{
			return;
		}

		$values = array_filter(
			$values,
			function ($type)
			{
				return in_array($type, ['reaction_count', 'reaction_score']);
			},
			ARRAY_FILTER_USE_KEY
		);

		$this->getActivityLogRepo()->log(
			$thread,
			$logDate,
			$values,
			$increment
		);
	}
}
