<?php

namespace XF\Job;

use XF\Entity\AttachmentData;
use XF\Service\Attachment\PreparerService;

class AttachmentOptimization extends AbstractImageOptimizationJob
{
	protected function getNextIds($start, $batch): array
	{
		$db = $this->app->db();

		return $db->fetchAllColumn($db->limit(
			"
				SELECT data_id
				FROM xf_attachment_data
				WHERE data_id > ?
					AND optimized = 0
				ORDER BY data_id
			",
			$batch
		), $start);
	}

	protected function optimizeById($id): void
	{
		/** @var AttachmentData $attachmentData */
		$attachmentData = $this->app->em()->find(AttachmentData::class, $id);

		/** @var PreparerService $attachmentPreparer */
		$attachmentPreparer = $this->app->service(PreparerService::class);
		$attachmentPreparer->optimizeExistingAttachment($attachmentData);
	}

	protected function getStatusType(): string
	{
		return \XF::phrase('attachments');
	}
}
