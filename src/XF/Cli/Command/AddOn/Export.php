<?php

namespace XF\Cli\Command\AddOn;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\AddOn\AddOn;
use XF\Cli\Command\AbstractCommand;
use XF\Cli\Command\AddOnActionTrait;
use XF\Cli\Command\JobRunnerTrait;
use XF\Service\AddOn\ExporterService;

use function count;

class Export extends AbstractCommand
{
	use AddOnActionTrait;
	use JobRunnerTrait;

	protected function configure()
	{
		$this
			->setName('xf-addon:export')
			->setDescription('Exports the XML files for an add-on')
			->addArgument(
				'id',
				InputArgument::REQUIRED,
				'Add-On ID'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$id = $input->getArgument('id');

		$addOn = $this->checkEditableAddOn($id, $error);
		if (!$addOn && $id == 'XF')
		{
			$addOn = new AddOn(
				\XF::em()->find(\XF\Entity\AddOn::class, 'XF'),
				\XF::app()->addOnManager()
			);
		}
		if (!$addOn)
		{
			$output->writeln('<error>' . $error . '</error>');
			return 1;
		}

		$output->writeln(["", "Exporting data for {$addOn->title} to {$addOn->getDataDirectory()}."]);

		/** @var ExporterService $exporterService */
		$exporterService = \XF::app()->service(ExporterService::class, $addOn);

		$containers = $exporterService->getContainers();

		if ($containers)
		{
			$progress = new ProgressBar($output, count($containers));
		}
		else
		{
			$progress = null;
		}

		foreach ($containers AS $containerName)
		{
			if ($progress)
			{
				$progress->setMessage("Writing $containerName.xml");
				$progress->advance();
			}

			$exporterService->export($containerName);
		}

		$output->writeln(["", "Written successfully."]);

		return 0;
	}
}
