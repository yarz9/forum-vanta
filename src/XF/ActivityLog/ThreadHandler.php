<?php

namespace XF\ActivityLog;

use XF\Entity\Thread;
use XF\Mvc\Entity\Entity;

/**
 * @extends AbstractHandler<Thread>
 */
class ThreadHandler extends AbstractHandler
{
	protected function getReplyMetrics(Entity $content): array
	{
		return $this->getReplyMetricsSimple(
			'xf_post',
			'post_date',
			'thread_id = ?',
			[$content->getEntityId()]
		);
	}

	protected function getReactionMetrics(Entity $content): array
	{
		$firstPost = $content->FirstPost;
		if (!$firstPost)
		{
			return [];
		}

		return parent::getReactionMetrics($firstPost);
	}
}
