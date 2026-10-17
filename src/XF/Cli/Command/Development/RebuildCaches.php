<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;
use XF\Cli\Command\JobRunnerTrait;
use XF\Job\CoreCacheRebuild;

class RebuildCaches extends AbstractCommand
{
	use JobRunnerTrait;
	use RequiresDevModeTrait;

	protected function configure()
	{
		$this
			->setName('xf-dev:rebuild-caches')
			->setDescription('Rebuilds various caches');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->setupAndRunJob('xfDevCoreCacheRebuild', CoreCacheRebuild::class);

		$output->writeln("Miscellaneous caches rebuilt.");

		return 0;
	}
}
