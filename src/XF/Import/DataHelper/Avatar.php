<?php

namespace XF\Import\DataHelper;

use XF\Entity\User;
use XF\Service\User\AvatarService;
use XF\Util\File;

class Avatar extends AbstractHelper
{
	public function copyFinalAvatarFile($sourceFile, $size, User $user)
	{
		$targetPath = $user->getAbstractedCustomAvatarPath($size);
		return File::copyFileToAbstractedPath($sourceFile, $targetPath);
	}

	public function copyFinalAvatarFiles(array $sourceFileMap, User $user)
	{
		$success = true;
		foreach ($sourceFileMap AS $size => $sourceFile)
		{
			if (!$this->copyFinalAvatarFile($sourceFile, $size, $user))
			{
				$success = false;
				break;
			}
		}

		return $success;
	}

	public function setAvatarFromFile($sourceFile, User $user)
	{
		/** @var AvatarService $avatarService */
		$avatarService = $this->dataManager->app()->service(AvatarService::class, $user);
		$avatarService->logIp(false);
		$avatarService->logChange(false);
		$avatarService->silentRunning(true);

		if ($avatarService->setImage($sourceFile))
		{
			$avatarService->updateAvatar();
			return true;
		}

		return false;
	}
}
