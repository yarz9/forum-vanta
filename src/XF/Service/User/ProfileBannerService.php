<?php

namespace XF\Service\User;

use XF\App;
use XF\Behavior\ChangeLoggable;
use XF\Entity\User;
use XF\Http\Upload;
use XF\Repository\IpRepository;
use XF\Service\AbstractService;
use XF\Util\File;

use function count, in_array;

class ProfileBannerService extends AbstractService
{
	/**
	 * @var User
	 */
	protected $user;

	protected $logIp = true;
	protected $logChange = true;

	protected $fileName;

	protected $type;

	protected $error = null;

	protected $allowedTypes = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

	protected $sizeMap;

	protected $throwErrors = true;

	public function __construct(App $app, User $user)
	{
		parent::__construct($app);
		$this->setUser($user);

		$this->sizeMap = $this->app->container('profileBannerSizeMap');
	}

	protected function setUser(User $user)
	{
		if ($user->user_id)
		{
			$this->user = $user;
		}
		else
		{
			throw new \LogicException("User must be saved");
		}
	}

	public function logIp($logIp)
	{
		$this->logIp = $logIp;
	}

	public function logChange($logChange)
	{
		$this->logChange = $logChange;
	}

	public function getError()
	{
		return $this->error;
	}

	public function silentRunning($runSilent)
	{
		$this->throwErrors = !$runSilent;
	}

	public function setImage($fileName)
	{
		if (!$this->validateImageAsBanner($fileName, $error))
		{
			$this->error = $error;
			$this->fileName = null;
			return false;
		}

		$this->fileName = $fileName;
		return true;
	}

	public function setImageFromUpload(Upload $upload)
	{
		$upload->requireImage();

		if (!$upload->isValid($errors))
		{
			$this->error = reset($errors);
			return false;
		}

		return $this->setImage($upload->getTempFile());
	}

	public function setImageFromExisting()
	{
		$path = $this->user->Profile->getAbstractedBannerPath('l');
		if (!$this->app->fs()->has($path))
		{
			return $this->throwException(new \InvalidArgumentException("User does not have an 'l' banner ($path)"));
		}

		$tempFile = File::copyAbstractedPathToTempFile($path);
		return $this->setImage($tempFile);
	}

	public function optimizeExistingBanner(): void
	{
		if ($this->app->options()->imageOptimization !== 'optimize')
		{
			return;
		}

		$this->setImageFromExisting();

		$imageManager = $this->app->imageManager();

		$image = $imageManager->imageFromFile($this->fileName);
		if (!$image)
		{
			return;
		}

		$success = $image->optimizeImage($this->fileName);
		if ($success)
		{
			$this->updateBanner();
		}
	}

	public function validateImageAsBanner($fileName, &$error = null)
	{
		$error = null;

		if (!file_exists($fileName))
		{
			return $this->throwException(new \InvalidArgumentException("Invalid file '$fileName' passed to banner service"));
		}
		if (!is_readable($fileName))
		{
			return $this->throwException(new \InvalidArgumentException("'$fileName' passed to banner service is not readable"));
		}

		$imageInfo = filesize($fileName) ? @getimagesize($fileName) : false;
		if (!$imageInfo)
		{
			$error = \XF::phrase('provided_file_is_not_valid_image');
			return false;
		}

		$type = $imageInfo[2];
		if (!in_array($type, $this->allowedTypes))
		{
			$error = \XF::phrase('provided_file_is_not_valid_image');
			return false;
		}

		$width = $imageInfo[0];
		$height = $imageInfo[1];

		if (!$this->app->imageManager()->canResize($width, $height))
		{
			$error = \XF::phrase('uploaded_image_is_too_big');
			return false;
		}

		$this->type = $type;

		return true;
	}

	public function updateBanner()
	{
		if (!$this->fileName)
		{
			return $this->throwException(new \LogicException("No source file for banner set"));
		}
		if (!$this->user->exists())
		{
			return $this->throwException(new \LogicException("User does not exist, cannot update banner"));
		}

		$imageManager = $this->app->imageManager();
		$baseImage = $imageManager->imageFromFile($this->fileName);
		if (!$baseImage)
		{
			return false;
		}

		$isOptimized = $baseImage->getType() === IMAGETYPE_WEBP;
		unset($baseImage);

		$outputFiles = [];

		foreach ($this->sizeMap AS $size => $width)
		{
			$image = $imageManager->imageFromFile($this->fileName);
			if (!$image)
			{
				continue;
			}

			$image->resizeWidth($width, true);

			$newTempFile = File::getTempFile();
			if ($newTempFile && $image->save($newTempFile, null, 95))
			{
				$outputFiles[$size] = $newTempFile;
			}
			unset($image);
		}

		if (count($outputFiles) != count($this->sizeMap))
		{
			return $this->throwException(new \RuntimeException("Failed to save image to temporary file; image may be corrupt or check internal_data/data permissions"));
		}

		foreach ($outputFiles AS $code => $file)
		{
			$dataFile = $this->user->Profile->getAbstractedBannerPath($code);
			File::copyFileToAbstractedPath($file, $dataFile);
		}

		$profile = $this->user->Profile;
		$profile->bulkSet([
			'banner_date' => \XF::$time,
			'banner_optimized' => $isOptimized,
			'banner_position_y' => 50,
		]);

		if ($this->logChange == false)
		{
			$profile->getBehavior(ChangeLoggable::class)->setOption('enabled', false);
		}

		$profile->save();

		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
			$this->writeIpLog('update', $ip);
		}

		return true;
	}

	public function setPosition($y)
	{
		$profile = $this->user->Profile;
		$profile->bulkSet([
			'banner_position_y' => $y,
		]);

		$profile->save();

		return true;
	}

	public function deleteBanner()
	{
		$this->deleteBannerFiles();

		$profile = $this->user->Profile;
		$profile->bulkSet([
			'banner_date' => 0,
			'banner_optimized' => false,
			'banner_position_y' => null,
		]);

		$profile->save();

		if ($this->logIp)
		{
			$ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
			$this->writeIpLog('delete', $ip);
		}

		return true;
	}

	public function deleteBannerForUserDelete()
	{
		$this->deleteBannerFiles();

		return true;
	}

	protected function deleteBannerFiles()
	{
		if ($this->user->Profile->banner_date)
		{
			foreach ($this->sizeMap AS $code => $size)
			{
				File::deleteFromAbstractedPath($this->user->Profile->getAbstractedBannerPath($code));
			}
		}
	}

	protected function writeIpLog($action, $ip)
	{
		$user = $this->user;

		/** @var IpRepository $ipRepo */
		$ipRepo = $this->repository(IpRepository::class);
		$ipRepo->logIp(\XF::visitor()->user_id, $ip, 'user', $user->user_id, 'profile_banner_' . $action);
	}

	/**
	 * @param \Exception $error
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function throwException(\Exception $error)
	{
		if ($this->throwErrors)
		{
			throw $error;
		}
		else
		{
			return false;
		}
	}
}
