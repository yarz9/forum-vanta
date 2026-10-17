<?php

namespace XF\TrendingContent;

use XF\Entity\Thread;
use XF\Mvc\Entity\AbstractCollection;
use XF\Repository\AttachmentRepository;

use function in_array;

/**
 * @extends AbstractHandler<Thread>
 */
class ThreadHandler extends AbstractHandler
{
	public function getEntityWith(string $style): array
	{
		$visitor = \XF::visitor();

		$with = [
			'Forum',
			'Forum.Node.Permissions|' . $visitor->permission_combination_id,
			'User',
		];

		if (
			in_array($style, ['article', 'carousel'], true) ||
			$this->areAttachmentsHydratedForStyle($style)
		)
		{
			$with[] = 'FirstPost';
		}

		return $with;
	}

	public function filterContent(AbstractCollection $content): AbstractCollection
	{
		return $content->filter(
			function (Thread $item): bool
			{
				return $item->canView() && !$item->isIgnored();
			}
		);
	}

	public function addAttachmentsToContent(AbstractCollection $content): void
	{
		$firstPosts = $content
			->filter(function (Thread $thread): bool
			{
				return $thread->FirstPost !== null;
			})
			->pluckNamed('FirstPost', 'first_post_id');

		$attachmentRepo = \XF::repository(AttachmentRepository::class);
		$attachmentRepo->addAttachmentsToContent($firstPosts, 'post');
	}
}
