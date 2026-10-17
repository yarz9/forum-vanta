<?php

namespace XF\Job;

use XF\Entity\AttachmentData;
use XF\Service\Attachment\PreparerService;
use XF\Util\File;

class AttachmentThumb extends AbstractJob
{
	protected $defaultData = [
		'start' => 0,
		'batch' => 100,
	];

	public function run($maxRunTime)
	{
		$startTime = microtime(true);

		$db = $this->app->db();
		$em = $this->app->em();
		$imageManager = $this->app->imageManager();

		$ids = $db->fetchAllColumn($db->limit(
			"
				SELECT data_id
				FROM xf_attachment_data
				WHERE data_id > ?
				ORDER BY data_id
			",
			$this->data['batch']
		), $this->data['start']);
		if (!$ids)
		{
			return $this->complete();
		}

		$done = 0;

		foreach ($ids AS $id)
		{
			$this->data['start'] = $id;

			/** @var AttachmentData $attachData */
			$attachData = $em->find(AttachmentData::class, $id);
			$abstractedPath = $attachData->getAbstractedDataPath();

			if (
				$attachData && $attachData->width && $attachData->height
				&& $imageManager->canResize($attachData->width, $attachData->height)
				&& $this->app->fs()->has($abstractedPath)
			)
			{
				$tempFile = File::copyAbstractedPathToTempFile($abstractedPath);
				// temp files are automatically cleaned up at the end of the request
				/** @var PreparerService $insertService */
				$insertService = \XF::app()->service(PreparerService::class);
				$tempThumb = $insertService->generateAttachmentThumbnail($tempFile, $thumbWidth, $thumbHeight);
				if ($tempThumb)
				{
					$db->beginTransaction();

					$attachData->thumbnail_width = $thumbWidth;
					$attachData->thumbnail_height = $thumbHeight;
					$attachData->save(true, false);

					$thumbPath = $attachData->getAbstractedThumbnailPath();
					try
					{
						File::copyFileToAbstractedPath($tempThumb, $thumbPath);
						$db->commit();
					}
					catch (\Exception $e)
					{
						$db->rollback();
						$this->app->logException($e, false, "Thumb rebuild for #$id: ");
					}
				}
			}

			$done++;

			if (microtime(true) - $startTime >= $maxRunTime)
			{
				break;
			}
		}

		File::cleanUpTempFiles();

		$this->data['batch'] = $this->calculateOptimalBatch($this->data['batch'], $done, $startTime, $maxRunTime, 1000);

		return $this->resume();
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('rebuilding');
		$typePhrase = \XF::phrase('attachment_thumbnails');
		return sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, $this->data['start']);
	}

	public function canCancel()
	{
		return true;
	}

	public function canTriggerByChoice()
	{
		return true;
	}
}
