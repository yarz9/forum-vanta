<?php

namespace XF\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Install\Data\MySql;

class RepairDb extends AbstractCommand
{
	protected function configure()
	{
		$this
			->setName('xf:repair-db')
			->setDescription('Fixes missing database tables')
			->addOption(
				'fix',
				null,
				InputOption::VALUE_NONE,
				'If set, any missing tables will be created. Without this, missing tables will just be listed.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$app = \XF::app();

		$db = $app->db();

		$sm = $db->getSchemaManager();

		$missing = 0;

		$output->writeln('');
		$output->writeln('Checking database <blue>' . \XF::config('db')['dbname'] . '</blue> for missing tables...');

		$addons = ['XF' => new MySql()];

		$app->fire('addon_get_install_data', [&$addons]);

		foreach ($addons AS $addOnId => $data)
		{
			$output->write($addOnId . '...');
			$addonHasMissing = 0;

			foreach ($data->getTables() AS $table => $closure)
			{
				if (!$sm->tableExists($table))
				{
					if (!$addonHasMissing)
					{
						$output->writeln('');
					}
					$addonHasMissing++;
					$missing++;

					$output->writeln("\tMissing table: <red>{$table}</red>");

					if ($input->getOption('fix'))
					{
						$sm->createTable($table, $closure);
						$output->writeln("\t- <green>Created</green> table");

						$contentQuery = $data->getData()[$table] ?? null;

						if ($contentQuery)
						{
							$db->query($contentQuery);
							$output->writeln("\t- <green>Inserted</green> content");
						}
					}
				}
			}

			if (!$addonHasMissing)
			{
				$output->writeln(' OK');
			}
		}

		if (!$missing)
		{
			$output->writeln('No tables missing.');
		}
		else if (!$input->getOption('fix'))
		{
			$output->writeln('Use <green>--fix</green> option to restore these ' . $missing . ' tables.');
		}

		$output->writeln('');

		return Command::SUCCESS;
	}
}
