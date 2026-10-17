<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Behavior\DevOutputWritable;
use XF\Cli\Command\AbstractCommand;
use XF\Entity\Phrase;
use XF\Service\Phrase\CompileService;
use XF\Service\Phrase\GroupService;
use XF\Service\Phrase\RebuildService;

use function count;

class RecompilePhrases extends AbstractCommand
{
	use RequiresDevModeTrait;

	protected function configure()
	{
		$this
			->setName('xf-dev:recompile-phrases')
			->setDescription('Recompiles phrases');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$db = \XF::db();
		$em = \XF::em();
		$app = \XF::app();
		$start = microtime(true);

		$output->writeln("Rebuilding phrase map...");

		/** @var RebuildService $rebuildService */
		$rebuildService = $app->service(RebuildService::class);
		$rebuildService->rebuildFullPhraseMap();

		/** @var GroupService $groupService */
		$groupService = $app->service(GroupService::class);
		$groupService->compileAllPhraseGroups();

		$output->writeln("Recompiling phrases...");

		$phraseIds = $db->fetchAllColumn("
			SELECT phrase_id
			FROM xf_phrase
			ORDER BY phrase_id
		");

		$progress = new ProgressBar($output, count($phraseIds));
		$progress->start();

		/** @var CompileService $compileService */
		$compileService = $app->service(CompileService::class);

		foreach ($phraseIds AS $phraseId)
		{
			$progress->advance();

			/** @var Phrase $phrase */
			$phrase = $em->find(Phrase::class, $phraseId);
			if (!$phrase)
			{
				continue;
			}

			$phrase->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', false);

			$compileService->recompile($phrase);

			$em->clearEntityCache(); // workaround memory issues
		}

		$progress->finish();
		$output->writeln("");

		$output->writeln(sprintf("Phrases compiled. (%.02fs)", microtime(true) - $start));

		return 0;
	}
}
