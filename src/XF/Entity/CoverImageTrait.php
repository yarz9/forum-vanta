<?php

namespace XF\Entity;

use XF\Mvc\Entity\AbstractCollection;

use function strlen;

trait CoverImageTrait
{
	/**
	 * @param AbstractCollection<Attachment> $attachments
	 */
	protected function getCoverImageInternal(
		AbstractCollection $attachments,
		bool $canViewAttachments,
		?array $embedMetadata,
		string $bbCode
	): ?string
	{
		$attachments = $attachments->filter(
			function (Attachment $attachment) use ($canViewAttachments): bool
			{
				if ($attachment->type_grouping !== 'image')
				{
					return false;
				}

				return $canViewAttachments || $attachment->hasThumbnail();
			}
		);

		$embeddedAttachmentIds = $embedMetadata['attachments'] ?? [];
		$embeddedAttachments = $attachments->sortByList(
			array_keys($embeddedAttachmentIds)
		);
		foreach ($embeddedAttachments AS $attachment)
		{
			/** @var Attachment $attachment */
			$url = $canViewAttachments
				? $attachment->getDirectUrl(true)
				: $attachment->getThumbnailUrlFull();
			if (!$url)
			{
				continue;
			}

			return $url;
		}

		if (preg_match(
			'#\[img(?: [^]]*)?\](https?://.+)\[/img]#iU',
			$bbCode,
			$match
		))
		{
			$url = $match[1];
			$strFormatter = $this->app()->stringFormatter();

			$linkInfo = $strFormatter->getLinkClassTarget($url);
			if (!$linkInfo['local'])
			{
				$proxiedUrl = $strFormatter->getProxiedUrlIfActive('image', $url);
				if ($proxiedUrl)
				{
					$paths = \XF::app()->container('request.paths');
					$pather = \XF::app()->container('request.pather');

					if (strpos($proxiedUrl, $paths['base']) === 0)
					{
						$proxiedUrl = substr($proxiedUrl, strlen($paths['base']));
					}

					$url = $pather($proxiedUrl, 'canonical');
				}
			}

			return $url;
		}

		foreach ($attachments AS $attachment)
		{
			if ($embeddedAttachments[$attachment->attachment_id] ?? null)
			{
				continue;
			}

			$url = $canViewAttachments
				? $attachment->getDirectUrl(true)
				: $attachment->getThumbnailUrlFull();
			if (!$url)
			{
				continue;
			}

			return $url;
		}

		return null;
	}
}
