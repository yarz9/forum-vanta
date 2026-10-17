<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;
use XF\Cli\Command\JobRunnerTrait;
use XF\Job\IconUsage;

class AnalyzeIcons extends AbstractCommand
{
	use JobRunnerTrait;
	use RequiresDevModeTrait;

	protected function configure()
	{
		$this
			->setName('xf-dev:analyze-icons')
			->setDescription('Analyzes icon usage and rebuilds sprites.');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->setupAndRunJob('xfDevIconUsage', IconUsage::class);

		$output->writeln('Icons analyzed.');

		return 0;
	}
}
