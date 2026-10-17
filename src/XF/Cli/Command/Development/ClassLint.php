<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;
use XF\Util\File;

class ClassLint extends AbstractCommand
{
	use RequiresDevModeTrait;

	protected function configure()
	{
		$this
			->setName('xf-dev:class-lint')
			->setDescription('Checks that all classes can be loaded without conflicts');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$srcDir = \XF::getSourceDirectory() . \XF::$DS . 'XF';
		$dirIterator = File::getRecursiveDirectoryIterator($srcDir, null, null);
		$iterator = new \RegexIterator($dirIterator, '/^.+\.php$/');

		foreach ($iterator AS $file)
		{
			if (preg_match('#(^|\\\\|/)(tests|test|_templates)(\\\\|/|$)#i', $file->getPath()))
			{
				continue;
			}
			if (!preg_match('#^[A-Z].+\.php$#', $file->getFilename()))
			{
				continue;
			}

			require_once $file->getPathname();
		}

		return 0;
	}
}
