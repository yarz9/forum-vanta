<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;

class Info extends AbstractCommand
{
	protected function configure()
	{
		$this
			->setName('xf-dev:info')
			->setDescription('Get useful info about this XenForo installation.');
	}

	protected function writelnHeading($output, $text)
	{
		$output->writeln(sprintf("<red>%s</red>", $text));
	}

	protected function writelnValue($output, $key, $value)
	{
		$output->writeln(sprintf("|   %s: <blue>%s</blue>", $key, $value));
	}

	protected function yesNo($bool)
	{
		return $bool ? 'Yes' : 'No';
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$app = \XF::app();
		$config = $app->config();
		$options = $app->options();

		$upgrade = $app->db()->fetchRow('SELECT * FROM xf_upgrade_log ORDER BY version_id DESC LIMIT 1');

		$output->writeln('');

		$this->writelnHeading($output, 'App');
		$this->writelnValue($output, 'XF version  ', \XF::$versionId . ' <cyan>(' . \XF::$version . ')</cyan>');
		$this->writelnValue($output, 'Last upgrade', $upgrade['version_id'] . ' <cyan>(' . gmdate('Y-m-d', $upgrade['completion_date']) . ')</cyan>');

		$this->writelnHeading($output, 'Options');
		$this->writelnValue($output, 'boardUrl', $options['boardUrl']);
		$this->writelnValue($output, 'boardTitle', $options['boardTitle']);
		$this->writelnValue($output, 'boardUrlCanonical', $this->yesNo($options['boardUrlCanonical']));

		$this->writelnHeading($output, 'Database');
		$this->writelnValue($output, 'dbname', $config['db']['dbname']);
		$this->writelnValue($output, 'username', $config['db']['username']);
		$this->writelnValue($output, 'port', $config['db']['port']);

		$this->writelnHeading($output, 'Config');
		$this->writelnValue($output, 'enableMail', $this->yesNo($config['enableMail']));
		$this->writelnValue($output, 'enableListeners', $this->yesNo($config['enableListeners']));

		$this->writelnHeading($output, 'Cookies');
		$this->writelnValue($output, 'prefix', $config['cookie']['prefix']);

		$this->writelnHeading($output, 'Paths');
		$this->writelnValue($output, 'internalDataPath', $config['internalDataPath']);
		$this->writelnValue($output, 'externalDataPath', $config['externalDataPath']);
		$this->writelnValue($output, 'externalDataUrl', $config['externalDataUrl']);
		$this->writelnValue($output, 'localDataPath', $config['localDataPath']);
		$this->writelnValue($output, 'localDataUrl', $config['localDataUrl']);

		$this->writelnHeading($output, 'Development');
		$this->writelnValue($output, 'enabled', $this->yesNo($config['development']['enabled']));
		$this->writelnValue($output, 'defaultAddOn', $config['development']['defaultAddOn']);
		$this->writelnValue($output, 'skipAddons', implode(', ', $config['development']['skipAddOns'] ?? []) ?: '(none)');

		return 0;
	}
}
