<?php

namespace XF\Admin\View\Log\ImageProxy;

use League\Flysystem\FileNotFoundException;
use XF\Entity\ImageProxy;
use XF\Mvc\View;

class Image extends View
{
	public function renderRaw()
	{
		/** @var ImageProxy $image */
		$image = $this->params['image'];
		/** @var ImageProxy $image */
		$placeHolderImage = $this->params['placeHolderImage'];

		$proxyController = \XF::app()->proxy()->controller();
		$proxyController->applyImageResponseHeaders($this->response, $image, null);

		if ($image->isPlaceholder())
		{
			return $this->response->responseFile($image->getPlaceholderPath());
		}
		else
		{
			try
			{
				$resource = \XF::fs()->readStream($image->getAbstractedImagePath());
				return $this->response->responseStream($resource, $image->file_size);
			}
			catch (FileNotFoundException $e)
			{
				// the file was pruned mid-request
				return $this->response->responseFile($placeHolderImage->getPlaceholderPath());
			}
		}
	}
}
