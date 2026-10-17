<?php

namespace XF\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{
	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		$colors = [
			'red',
			'green',
			'yellow',
			'blue',
			'magenta',
			'cyan',
		];

		foreach ($colors AS $color)
		{
			$output->getFormatter()->setStyle($color, new OutputFormatterStyle($color));
		}

		return parent::initialize($input, $output);
	}
}
