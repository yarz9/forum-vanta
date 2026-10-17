<?php

namespace XF\FeaturedContent;

use XF\Entity\Thread;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Repository\AttachmentRepository;

use function in_array;

/**
 * @extends AbstractHandler<Thread>
 */
class ThreadHandler extends AbstractHandler
{
	public function getContentImage(
		Entity $content,
		?string $sizeCode = null
	): ?string
	{
		return $content->getCoverImage();
	}

	public function getContentSnippet(Entity $content): string
	{
		return $this->getSnippetFromString($content->FirstPost->message ?? null);
	}

	public function getContentStructuredData(Entity $content): array
	{
		return $content->getLdStructuredData($content->FirstPost);
	}

	public function shouldAutoFeature(Entity $content): bool
	{
		return $content->Forum->auto_feature ?? false;
	}

	public function getEntityWithForStyle(string $style): array
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

	protected function addAttachmentsToContent(
		AbstractCollection $imagelessContent,
		AbstractCollection $content
	): void
	{
		$firstPosts = $imagelessContent
			->filter(function (Thread $thread): bool
			{
				return $thread->FirstPost !== null;
			})
			->pluckNamed('FirstPost', 'first_post_id');

		$attachmentRepo = \XF::repository(AttachmentRepository::class);
		$attachmentRepo->addAttachmentsToContent($firstPosts, 'post');
	}
}
