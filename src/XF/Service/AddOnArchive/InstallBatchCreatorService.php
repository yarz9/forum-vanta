<?php

namespace XF\Service\AddOnArchive;

use XF\AddOn\Manager;
use XF\App;
use XF\Entity\AddOnInstallBatch;
use XF\Http\Upload;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class InstallBatchCreatorService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected $errors = [];

	/**
	 * @var Manager
	 */
	protected $addOnManager;

	/**
	 * @var AddOnInstallBatch
	 */
	protected $installBatch;

	public function __construct(App $app, Manager $manager)
	{
		parent::__construct($app);

		$this->addOnManager = $manager;
		$this->installBatch = $this->em()->create(AddOnInstallBatch::class);
	}

	public function addUpload(Upload $upload)
	{
		$fileName = $upload->getFileName();
		$upload->setAllowedExtensions(['zip']);

		if (!$upload->isValid($errors))
		{
			$this->errors[] = \XF::phrase('could_not_process_x_y', ['fileName' => $fileName, 'errors' => reset($errors)]);
			return false;
		}

		$tempFile = $upload->getTempFile();
		return $this->addArchive($tempFile, $fileName);
	}

	public function addArchive($tempFile, $fileName = null)
	{
		if (!$fileName)
		{
			$fileName = basename($tempFile);
		}

		if (!file_exists($tempFile))
		{
			$this->errors[] = \XF::phrase('could_not_open_x', ['fileName' => $fileName]);
			return false;
		}

		$indexBuilt = $this->setupAddOnZip($tempFile, $error);
		if (!$indexBuilt)
		{
			$this->errors[] = \XF::phrase('could_not_process_x_y', ['fileName' => $fileName, 'errors' => $error]);
			return false;
		}

		return true;
	}

	protected function setupAddOnZip($tempFile, &$error)
	{
		/** @var ValidatorService $validator */
		$validator = $this->service(ValidatorService::class, $tempFile);
		if (!$validator->validate($error))
		{
			return false;
		}

		$addOnId = $validator->getAddOnId();
		$json = $validator->getAddOnJson();

		$this->installBatch->addAddOn($addOnId, $json['title'], $json['version_id'], $json['version_string'], $tempFile);

		return true;
	}

	protected function _validate()
	{
		if ($this->errors)
		{
			return $this->errors;
		}

		$batch = $this->installBatch;
		$batch->preSave();

		// errors being triggered here likely indicates a bug on our part
		return $batch->getErrors();
	}

	protected function _save()
	{
		$batch = $this->installBatch;
		$batch->save();

		return $batch;
	}
}
