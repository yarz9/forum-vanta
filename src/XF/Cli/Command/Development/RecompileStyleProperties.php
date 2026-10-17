<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;
use XF\Service\StyleProperty\RebuildService;

class RecompileStyleProperties extends AbstractCommand
{
	use RequiresDevModeTrait;

	protected function configure()
	{
		$this
			->setName('xf-dev:recompile-style-properties')
			->setDescription('Recompiles style properties');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$app = \XF::app();
		$start = microtime(true);

		$output->writeln("Recompiling style properties...");

		/** @var RebuildService $rebuildService */
		$spRebuildService = $app->service(RebuildService::class);
		$spRebuildService->rebuildFullPropertyMap();
		$spRebuildService->rebuildPropertyStyleCache();

		$output->writeln(sprintf("Style properties compiled. (%.02fs)", microtime(true) - $start));

		return 0;
	}
}
