<?php

namespace XF;

use function in_array, strlen;

class FileWrapper
{
	protected $filePath;
	protected $fileSize;
	protected $fileName;
	protected $extension;

	protected $isImage = null;
	protected $isOptimized = null;
	protected $imageInfo = null;
	protected $exif = null;

	public function __construct($filePath, $fileName = '')
	{
		if (!file_exists($filePath) || !is_readable($filePath))
		{
			throw new \InvalidArgumentException("File '$filePath' can not be read or found");
		}

		$this->filePath = $filePath;
		clearstatcache();
		$this->fileSize = filesize($filePath);
		$this->setFileName(strlen($fileName) ? $fileName : basename($filePath));
	}

	public function getFilePath()
	{
		return $this->filePath;
	}

	public function getFileSize()
	{
		return $this->fileSize;
	}

	public function setFileName($fileName)
	{
		if (!strlen($fileName))
		{
			throw new \InvalidArgumentException("A file name must be provided");
		}

		$this->fileName = $fileName;
		$this->extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
	}

	public function getFileName()
	{
		return $this->fileName;
	}

	public function getExtension()
	{
		return $this->extension;
	}

	public function isImage()
	{
		if ($this->isImage === null)
		{
			$this->analyzeImage();
		}

		return $this->isImage;
	}

	public function isOptimized()
	{
		if ($this->isOptimized === null)
		{
			$this->analyzeImage();
		}

		return $this->isOptimized;
	}

	public function getImageType()
	{
		return $this->isImage() ? $this->imageInfo[2] : null;
	}

	public function getImageWidth()
	{
		return $this->isImage() ? $this->imageInfo[0] : 0;
	}

	public function getImageHeight()
	{
		return $this->isImage() ? $this->imageInfo[1] : 0;
	}

	protected function analyzeImage()
	{
		$this->isImage = false;
		$this->isOptimized = false;

		if (!$this->fileSize)
		{
			return;
		}

		$map = $this->getImageExtensionMap();
		if (!isset($map[$this->extension]))
		{
			// require image extension to even try anything
			return;
		}

		$imageInfo = @getimagesize($this->filePath);
		if (!$imageInfo)
		{
			return;
		}

		$imageType = $imageInfo[2];
		if (!in_array($imageType, $map))
		{
			return;
		}

		if ($imageType != $map[$this->extension])
		{
			foreach ($map AS $newExtension => $extensionType)
			{
				if ($imageType == $extensionType)
				{
					$this->fileName .= ".$newExtension";
					break;
				}
			}
		}

		$this->isImage = true;
		$this->isOptimized = $imageType === IMAGETYPE_WEBP;
		$this->imageInfo = $imageInfo;

		if ($imageType == IMAGETYPE_JPEG && function_exists('exif_read_data'))
		{
			@ini_set('exif.encode_unicode', 'UTF-8');
			$exif = @exif_read_data($this->filePath, null, true) ?: [];
		}
		else
		{
			$exif = [];
		}

		$this->exif = array_replace_recursive($this->exif ?? [], $exif);
	}

	public function getExif()
	{
		if ($this->isImage === null)
		{
			$this->analyzeImage();
		}

		return $this->exif;
	}

	public function setExif(array $exif)
	{
		$this->exif = array_replace_recursive($this->exif ?? [], $exif);
	}

	protected function getImageExtensionMap()
	{
		return [
			'gif' => IMAGETYPE_GIF,
			'jpg' => IMAGETYPE_JPEG,
			'jpeg' => IMAGETYPE_JPEG,
			'jpe' => IMAGETYPE_JPEG,
			'png' => IMAGETYPE_PNG,
			'webp' => IMAGETYPE_WEBP,
		];
	}
}
