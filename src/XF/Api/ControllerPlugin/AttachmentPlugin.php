<?php

namespace XF\Api\ControllerPlugin;

use XF\Attachment\AbstractHandler;
use XF\Attachment\Manipulator;
use XF\Http\Upload;
use XF\Repository\AttachmentRepository;

class AttachmentPlugin extends AbstractPlugin
{
	public function uploadFile(
		Upload $upload,
		AbstractHandler $handler,
		array $context,
		$tempHash
	)
	{
		/** @var AttachmentRepository $attachmentRepo */
		$attachmentRepo = $this->repository(AttachmentRepository::class);

		/** @var Manipulator $manipulator */
		$class = \XF::extendClass(Manipulator::class);
		$manipulator = new $class($handler, $attachmentRepo, $context, $tempHash);

		if (!$manipulator->canUpload($uploadError))
		{
			throw $this->exception($this->error($uploadError));
		}

		$attachment = $manipulator->insertAttachmentFromUpload($upload, $error);
		if (!$attachment)
		{
			throw $this->exception($this->error($error));
		}

		return $attachment;
	}
}
