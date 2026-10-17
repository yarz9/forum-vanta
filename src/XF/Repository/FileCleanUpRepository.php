<?php

namespace XF\Repository;

use XF\Mvc\Entity\Repository;
use XF\Util\File;

use function in_array;

class FileCleanUpRepository extends Repository
{
	public function getAllowedDeletionPaths(string $addOnId): array
	{
		if ($addOnId === 'XF')
		{
			$allowedPaths = [
				'src/vendor/',
				'src/vendor-patch/',
				'src/XF/',
			];
		}
		else
		{
			$allowedPaths = [];

			$addOn = $this->app()->addOnManager()->getById($addOnId);
			$addOnDir = $addOn->getAddOnDirectory();

			if (file_exists($addOnDir) && is_dir($addOnDir))
			{
				$allowedPaths = [
					File::stripRootPathPrefix($addOnDir) . '/',
				];
			}
		}

		return $allowedPaths;
	}

	public function getDeletableFiles(array $allowedPaths, array $currentFiles): array
	{
		$filesToDelete = [];

		foreach ($allowedPaths AS $prefix)
		{
			$canonicalPath = File::canonicalizePath($prefix);
			$iterator = File::getRecursiveDirectoryIterator($canonicalPath);
			foreach ($iterator AS $file)
			{
				/** @var \SplFileInfo $file */
				if ($file->isDir())
				{
					continue;
				}

				$relativePath = File::stripRootPathPrefix($file->getPathname());
				if (!in_array($relativePath, $currentFiles))
				{
					$filesToDelete[] = $relativePath;
				}
			}
		}

		return $filesToDelete;
	}

	public function isFileValidForDeletion(string $addOnId, string $file, array $allowedPaths): bool
	{
		$undeletablePaths = [
			"src/addons/$addOnId/_output/",
			"src/addons/$addOnId/hashes.json",
		];

		if ($addOnId !== 'XF')
		{
			$undeletablePaths = array_replace([
				"src/addons/$addOnId/addon.json",
				"src/addons/$addOnId/build.json",
				"src/addons/$addOnId/_files/",
				"src/addons/$addOnId/_releases/",
			], $undeletablePaths);
		}

		foreach ($undeletablePaths AS $undeletablePath)
		{
			if (strpos($file, $undeletablePath) === 0)
			{
				return false;
			}
		}

		foreach ($allowedPaths AS $prefix)
		{
			if (strpos($file, $prefix) === 0)
			{
				return true;
			}
		}

		return false;
	}
}
