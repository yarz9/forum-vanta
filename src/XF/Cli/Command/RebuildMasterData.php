<?php

namespace XF\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Install\App;
use XF\Install\Helper;

class RebuildMasterData extends AbstractCommand implements CustomAppCommandInterface
{
	use JobRunnerTrait;

	public static function getCustomAppClass()
	{
		return App::class;
	}

	protected function configure()
	{
		$this
			->setName('xf:rebuild-master-data')
			->setDescription('Rebuilds the core XF master data.');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$installHelper = new Helper(\XF::app());
		$installHelper->insertRebuildJob('xfRebuildMaster');

		$startTime = microtime(true);

		$this->runJob('xfRebuildMaster', $output);

		$total = microtime(true) - $startTime;

		$output->writeln(sprintf("Master data rebuilt successfully. Time taken to import and rebuild: %.02fs", $total));

		return 0;
	}
}
