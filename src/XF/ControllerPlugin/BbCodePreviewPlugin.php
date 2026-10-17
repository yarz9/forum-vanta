<?php

namespace XF\ControllerPlugin;

use XF\Entity\User;
use XF\Repository\UserRepository;

class BbCodePreviewPlugin extends AbstractPlugin
{
	public function actionPreview($message, $context, ?User $user = null, $attachments = null, $canViewAttachments = true)
	{
		$user = $user ?: $this->repository(UserRepository::class)->getGuestUser();

		$viewParams = [
			'message' => $message,
			'context' => $context,
			'user' => $user,
			'attachments' => $attachments,
			'canViewAttachments' => $canViewAttachments,
		];
		return $this->view('XF:BbCodePreview\Preview', 'bb_code_preview', $viewParams);
	}
}
