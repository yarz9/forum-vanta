<?php

namespace XF\Import\DataHelper;

use XF\Entity\User;
use XF\Entity\UserProfile;
use XF\Service\User\ProfileBannerService;
use XF\Util\File;

class ProfileBanner extends AbstractHelper
{
	public function copyFinalBannerFile($sourceFile, $size, UserProfile $profile)
	{
		$targetPath = $profile->getAbstractedBannerPath($size);
		return File::copyFileToAbstractedPath($sourceFile, $targetPath);
	}

	public function copyFinalBannerFiles(array $sourceFileMap, UserProfile $profile)
	{
		$success = true;
		foreach ($sourceFileMap AS $size => $sourceFile)
		{
			if (!$this->copyFinalBannerFile($sourceFile, $size, $profile))
			{
				$success = false;
				break;
			}
		}

		return $success;
	}

	public function setBannerFromFile($sourceFile, User $user)
	{
		/** @var ProfileBannerService $bannerService */
		$bannerService = $this->dataManager->app()->service(ProfileBannerService::class, $user);
		$bannerService->logIp(false);
		$bannerService->logChange(false);
		$bannerService->silentRunning(true);

		if ($bannerService->setImage($sourceFile))
		{
			$bannerService->updateBanner();
			return true;
		}

		return false;
	}
}
