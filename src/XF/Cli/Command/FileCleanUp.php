<?php

namespace XF\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Repository\FileCleanUpRepository;
use XF\Util\File;

use function count;

class FileCleanUp extends Command
{
	use JobRunnerTrait;

	protected function configure(): void
	{
		$this
			->setName('xf:file-clean-up')
			->setDescription('Cleans up legacy files which are no longer used.')
			->addArgument(
				'addon',
				InputArgument::REQUIRED,
				'Add-on ID to clean up for'
			)
			->addOption(
				'dry-run',
				null,
				InputOption::VALUE_NONE,
				'List files to be deleted without taking any action.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$addOnId = $input->getArgument('addon');

		if ($addOnId === 'XF')
		{
			$currentHashesPath = \XF::getAddOnDirectory() . '/XF/hashes.json';
		}
		else
		{
			$addOn = \XF::app()->addOnManager()->getById($addOnId);
			if (!$addOn || !$addOn->isInstalled())
			{
				$io->error("No add-on with ID '$addOnId' could be found.");
				return Command::FAILURE;
			}

			$currentHashesPath = $addOn->getHashesPath();
		}

		if (!$currentHashesPath || !is_file($currentHashesPath))
		{
			$io->error(\XF::phrase('current_hashes_file_cannot_be_determined'));
			return Command::FAILURE;
		}

		$currentHashes = json_decode(file_get_contents($currentHashesPath), true) ?: [];
		$currentFiles = array_keys($currentHashes);

		$fileCleanUpRepo = \XF::repository(FileCleanUpRepository::class);

		$allowedPaths = $fileCleanUpRepo->getAllowedDeletionPaths($addOnId);
		$deletableFiles = $fileCleanUpRepo->getDeletableFiles($allowedPaths, $currentFiles);
		$filteredFiles = [];

		foreach ($deletableFiles AS $file)
		{
			if ($fileCleanUpRepo->isFileValidForDeletion($addOnId, $file, $allowedPaths))
			{
				$filteredFiles[] = $file;
			}
		}

		sort($filteredFiles);
		$count = count($filteredFiles);
		$countText = \XF::language()->numberFormat($count);

		if (!$count)
		{
			$io->success(\XF::phrase('could_not_find_any_files_to_be_deleted'));
			return Command::SUCCESS;
		}

		$io->info(\XF::phrase('found_x_files_to_be_deleted', ['count' => $countText]));
		$io->listing($filteredFiles);

		if ($input->getOption('dry-run'))
		{
			$io->warning(\XF::phrase('dry_run_was_used_no_files_will_be_deleted'));
			return Command::SUCCESS;
		}

		if (!$io->confirm(\XF::phrase('you_sure_you_want_to_clean_up_x_files_for_y', ['count' => $countText, 'addOnId' => $addOnId])))
		{
			return Command::SUCCESS;
		}

		$io->section(\XF::phrase('deleting_legacy_files...'));

		foreach ($io->progressIterate($deletableFiles, $count) AS $file)
		{
			$path = File::canonicalizePath($file);
			if (file_exists($path) && !File::isWritable($path))
			{
				break;
			}

			@unlink($path);
		}

		$io->success(\XF::phrase('file_clean_up_has_been_completed'));

		return Command::SUCCESS;
	}
}
