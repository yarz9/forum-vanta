<?php

namespace XF\Service\FeaturedContent;

use XF\Http\Upload;
use XF\Util\File;

use function in_array;

trait ImageTrait
{
	/**
	 * @var int
	 */
	protected $imageTargetSize = 1280;

	/**
	 * @var string|null
	 */
	protected $imageFileName;

	/**
	 * @var Upload|null
	 */
	protected $imageUpload;

	/**
	 * @var array
	 */
	protected $image = [];

	/**
	 * @var bool
	 */
	protected $deleteImage = false;

	/**
	 * @var int[]
	 */
	protected $allowedImageTypes = [
		IMAGETYPE_GIF,
		IMAGETYPE_JPEG,
		IMAGETYPE_PNG,
		IMAGETYPE_WEBP,
	];

	public function setImage(string $fileName)
	{
		if (!file_exists($fileName))
		{
			throw new \InvalidArgumentException(
				"The file '{$fileName}' does not exist"
			);
		}

		if (!is_readable($fileName))
		{
			throw new \InvalidArgumentException(
				"The file '{$fileName}' is not readable"
			);
		}

		$this->imageFileName = $fileName;
	}

	public function setImageFromUpload(Upload $upload)
	{
		$this->imageUpload = $upload;
	}

	public function setDeleteImage($delete = true)
	{
		$this->deleteImage = $delete;
	}

	protected function validateImage(&$error = null): bool
	{
		if (!$this->imageFileName && !$this->imageUpload)
		{
			return true;
		}

		if ($this->imageUpload)
		{
			$this->imageUpload->requireImage();
			if (!$this->imageUpload->isValid($errors))
			{
				$error = reset($errors);
				return false;
			}

			$fileName = $this->imageUpload->getTempFile();
		}
		else
		{
			$fileName = $this->imageFileName;
		}

		$imageInfo = filesize($fileName) ? @getimagesize($fileName) : false;
		if (!$imageInfo)
		{
			$error = \XF::phrase('provided_file_is_not_valid_image');
			return false;
		}

		$width = $imageInfo[0];
		$height = $imageInfo[1];
		$type = $imageInfo[2];

		if (!in_array($type, $this->allowedImageTypes))
		{
			$error = \XF::phrase('provided_file_is_not_valid_image');
			return false;
		}

		if (!$this->app->imageManager()->canResize($width, $height))
		{
			$error = \XF::phrase('uploaded_image_is_too_big');
			return false;
		}

		$this->imageFileName = null;
		$this->imageUpload = null;
		$this->image = [
			'fileName' => $fileName,
			'width' => $width,
			'height' => $height,
			'type' => $type,
		];

		return true;
	}

	protected function saveImage(): bool
	{
		if (!$this->image)
		{
			return false;
		}

		if (
			$this->image['width'] != $this->imageTargetSize ||
			$this->image['height'] != $this->imageTargetSize
		)
		{
			$imageManager = $this->app->imageManager();
			$image = $imageManager->imageFromFile($this->image['fileName']);
			if (!$image)
			{
				return false;
			}

			$image->resizeAndCrop($this->imageTargetSize);

			$tempFile = File::getTempFile();
			if ($tempFile && $image->save($tempFile))
			{
				$outputFile = $tempFile;
			}
			else
			{
				throw new \RuntimeException(
					'Failed to save image to temporary file; check internal_data/data permissions'
				);
			}
		}
		else
		{
			$outputFile = $this->image['fileName'];
		}

		File::copyFileToAbstractedPath(
			$outputFile,
			$this->feature->getAbstractedImagePath()
		);
		$this->feature->fastUpdate('image_date', \XF::$time);

		return true;
	}

	protected function deleteImage(): bool
	{
		if (!$this->deleteImage)
		{
			return false;
		}

		File::deleteFromAbstractedPath(
			$this->feature->getAbstractedImagePath()
		);
		$this->feature->fastUpdate('image_date', 0);

		return true;
	}
}
