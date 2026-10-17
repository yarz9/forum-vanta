<?php

namespace XF\Cli\Command;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use XF\Db\Schema\Alter;
use XF\Job\SearchRebuild;
use XF\Repository\OptionRepository;
use XF\Search\Source\MySqlFt;

class ConvertSearchInnoDb extends AbstractCommand
{
	use JobRunnerTrait;

	/**
	 * @var string
	 */
	protected static $defaultDescription = 'Converts the MySQL search index to InnoDB';

	protected function configure(): void
	{
		$this->setName('xf:convert-search-innodb');
	}

	protected function execute(
		InputInterface $input,
		OutputInterface $output
	): int
	{
		if (\XF::app()->config('searchInnoDb'))
		{
			$output->writeln(
				'<error>The MySQL search index has already been configured to use the InnoDB engine.</error>'
			);
			return static::FAILURE;
		}

		$db = \XF::db();

		$currentEngine = $db->fetchOne(
			'SELECT engine
				FROM information_schema.tables
				WHERE table_name = ?',
			'xf_search_index'
		);
		if ($currentEngine === 'InnoDB')
		{
			$output->writeln([
				'<error>The MySQL search index is already using the InnoDB engine.</error>',
				'',
				'<info>You must add the following to your src/config.php file:</info>',
				'<info>$config[\'searchInnoDb\'] = true;</info>',
			]);
			return static::FAILURE;
		}

		/** @var QuestionHelper $questionHelper */
		$questionHelper = $this->getHelper('question');

		$output->writeLn(
			'<info>The MySQL search index will be converted to the InnoDB engine.</info>'
		);

		if ($currentEngine !== 'MyISAM')
		{
			$output->writeln([
				'<warning>Warning: The MySQL search index is not using the MyISAM engine.</warning>',
			]);
		}

		$output->writeLn([
			'',
			'If you proceed, the MySQL search index will be emptied, converted, and optionally rebuilt.',
			'During this time, the search system may be unavailable or return incomplete results.',
			'',
		]);

		$question = new ConfirmationQuestion(
			"<question>Would you like to proceed? [y/N]</question> ",
			false
		);
		if (!$questionHelper->ask($input, $output, $question))
		{
			return static::FAILURE;
		}

		$output->writeln([
			'',
			'<info>Beginning the conversion...</info>',
			'',
		]);

		$output->writeln('Emptying the MySQL search index...');
		$db->emptyTable('xf_search_index');

		$output->writeln('Converting the MySQL search index to InnoDB...');
		$sm = $db->getSchemaManager();
		$sm->alterTable('xf_search_index', function (Alter $table)
		{
			$table->engine('InnoDB');
		});

		$searchSource = \XF::app()->get('search.source');
		$needsRebuild = $searchSource instanceof MySqlFt;
		if ($needsRebuild)
		{
			$minWordLength = (string) $db->fetchOne('SELECT @@innodb_ft_min_token_size');
			if (\XF::options()->searchMinWordLength !== $minWordLength)
			{
				$output->writeln("Updating search minimum word length to {$minWordLength}...");
				$optionRepo = \XF::repository(OptionRepository::class);
				$optionRepo->updateOption('searchMinWordLength', $minWordLength);
			}

			$output->writeln('');
			$question = new ConfirmationQuestion(
				"<question>The search index must be rebuilt. Would you like to rebuild it now? [y/N]</question> ",
				false
			);
			if ($questionHelper->ask($input, $output, $question))
			{
				$output->writeln('Rebuilding the search index...');
				$this->setupAndRunJob('searchRebuild', SearchRebuild::class);
				$needsRebuild = false;
			}
		}
		else
		{
			$output->writeln(
				'The search system is not using MySQL. Skipping rebuild...'
			);
		}

		$output->writeln([
			'',
			'<info>Conversion complete!</info>',
		]);

		if (!\XF::app()->config('searchInnoDb'))
		{
			$output->writeln([
				'',
				'<info>You must add the following to your src/config.php file:</info>',
				'<info>$config[\'searchInnoDb\'] = true;</info>',
			]);
		}

		if ($needsRebuild)
		{
			$output->writeln([
				'',
				'<info>The search index must be rebuilt.</info>',
				'<info>You may rebuild it from the control panel or by running the xf-rebuild:search command.</info>',
			]);
		}

		return static::SUCCESS;
	}
}
