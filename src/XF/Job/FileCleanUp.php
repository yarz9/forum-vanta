<?php

namespace XF\Job;

use XF\Repository\FileCleanUpRepository;
use XF\Timer;
use XF\Util\File;

class FileCleanUp extends AbstractJob
{
	protected $defaultData = [
		'addon_id' => null,
		'current_hashes' => null,
		'current_files' => [],
		'allow_paths' => null,
		'deletable_files' => null,
		'deleted_files' => [],
	];

	public function run($maxRunTime): JobResult
	{
		if (!$addOnId = $this->data['addon_id'])
		{
			return $this->complete();
		}

		if ($addOnId === 'XF')
		{
			$currentHashesPath = \XF::getAddOnDirectory() . '/XF/hashes.json';
		}
		else
		{
			$addOn = $this->app->addOnManager()->getById($addOnId);
			if (!$addOn)
			{
				return $this->complete();
			}

			$currentHashesPath = $addOn->getHashesPath();
		}

		if (!$currentHashesPath || !is_file($currentHashesPath))
		{
			return $this->complete();
		}

		$this->data['current_hashes'] = json_decode(file_get_contents($currentHashesPath), true);

		$this->stepInitialize();
		$this->stepInitializeFiles();

		return $this->stepDeleteFiles($maxRunTime);
	}

	protected function stepInitialize(): void
	{
		if ($this->data['allow_paths'] !== null)
		{
			return;
		}

		$this->data['current_files'] = array_keys($this->data['current_hashes']);

		$this->data['allow_paths'] = $this->app
			->repository(FileCleanUpRepository::class)
			->getAllowedDeletionPaths($this->data['addon_id']);
	}

	protected function stepInitializeFiles(): void
	{
		if ($this->data['deletable_files'] !== null)
		{
			return;
		}

		$filesToDelete = $this->app
			->repository(FileCleanUpRepository::class)
			->getDeletableFiles($this->data['allow_paths'], $this->data['current_files']);

		$this->data['deletable_files'] = $filesToDelete;
	}

	protected function stepDeleteFiles(float $maxRunTime): JobResult
	{
		$timer = new Timer($maxRunTime);
		$writeErrors = false;

		foreach ($this->data['deletable_files'] AS $key => $file)
		{
			unset($this->data['deletable_files'][$key]);

			if (!$this->isValidForDeletion($file))
			{
				continue;
			}

			$path = File::canonicalizePath($file);
			if (file_exists($path) && !File::isWritable($path))
			{
				$writeErrors = true;
				break;
			}

			@unlink($path);
			$this->data['deleted_files'][] = $file;

			if ($timer->limitExceeded())
			{
				break;
			}
		}

		if ($writeErrors)
		{
			\XF::logError(\XF::phrase('file_clean_up_job_failed_for_x_due_to_write_permissions', ['addon_id' => $this->data['addon_id']]));
			return $this->complete();
		}

		return $this->data['deletable_files'] ? $this->resume() : $this->complete();
	}

	protected function isValidForDeletion(string $file): bool
	{
		return $this->app
			->repository(FileCleanUpRepository::class)
			->isFileValidForDeletion($this->data['addon_id'], $file, $this->data['allow_paths']);
	}

	public function getStatusMessage(): string
	{
		return \XF::phrase('deleting_legacy_files...');
	}

	public function canCancel(): bool
	{
		return false;
	}

	public function canTriggerByChoice(): bool
	{
		return false;
	}
}
