<?php

namespace XF\Job;

use XF\Entity\LinkableInterface;
use XF\IndexNow\Api;
use XF\Mvc\Entity\Entity;
use XF\Repository\UserRepository;

use function get_class;

class ContentIndexNow extends AbstractJob
{
	use Retryable;

	protected $defaultData = [
		'content_type' => null,
		'content_id' => null,
		'content_url' => null,
	];

	public function run($maxRunTime): JobResult
	{
		$options = \XF::options();

		if (!$options->useFriendlyUrls || !$options->indexNow['enabled'])
		{
			return $this->complete();
		}

		if ((!$this->data['content_type'] || !$this->data['content_id']) && !$this->data['content_url'])
		{
			throw new \InvalidArgumentException('Cannot make a request to IndexNow without a valid content type and ID or URL.');
		}

		$contentType = $this->data['content_type'];
		$contentId = $this->data['content_id'];
		$contentUrl = $this->data['content_url'];

		if (!$contentUrl)
		{
			/** @var Entity $content */
			$content = $this->app->findByContentType($contentType, $contentId);

			if (!$content instanceof Entity)
			{
				throw new \InvalidArgumentException('Cannot make a request to IndexNow without a valid content entity.');
			}

			if (!method_exists($content, 'canView'))
			{
				throw new \LogicException(
					'Could not determine content viewability; Implement XF\Entity\ViewableInterface for ' . get_class($content)
				);
			}

			if (!$content instanceof LinkableInterface)
			{
				throw new \LogicException(
					'Implement XF\Entity\LinkableInterface for ' . get_class($content)
				);
			}

			$guestUser = \XF::repository(UserRepository::class)->getGuestUser();
			$canView = \XF::asVisitor($guestUser, function () use ($guestUser, $content)
			{
				if (!$guestUser->hasPermission('general', 'view'))
				{
					return false;
				}

				return $content->canView($content);
			});
			if (!$canView)
			{
				return $this->complete();
			}

			$contentUrl = $content->getContentUrl(true);
		}

		$class = \XF::extendClass(Api::class);
		$api = new $class();
		$result = $api->index($contentUrl);

		if (!$result)
		{
			$this->attemptLaterOrComplete();
		}

		return $this->complete();
	}
}
