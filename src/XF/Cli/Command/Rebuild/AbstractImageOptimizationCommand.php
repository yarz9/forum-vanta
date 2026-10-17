<?php

namespace XF\Cli\Command\Rebuild;

use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractImageOptimizationCommand extends AbstractRebuildCommand
{
	public function setupAndRunJob($uniqueId, $jobClass, array $params = [], ?OutputInterface $output = null)
	{
		if (\XF::options()->imageOptimization !== 'optimize')
		{
			$output->writeln(
				'<error>Image optimization is not enabled. Please enable it in the control panel options to use this command.</error>'
			);
			return;
		}

		parent::setupAndRunJob($uniqueId, $jobClass, $params, $output);
	}
}
