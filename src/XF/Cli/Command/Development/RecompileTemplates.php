<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Behavior\DevOutputWritable;
use XF\Cli\Command\AbstractCommand;
use XF\Entity\Template;
use XF\Service\StyleProperty\RebuildService;
use XF\Service\Template\CompileService;

use function count;

class RecompileTemplates extends AbstractCommand
{
	use RequiresDevModeTrait;

	protected function configure()
	{
		$this
			->setName('xf-dev:recompile-templates')
			->setDescription('Recompiles parsed templates');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$db = \XF::db();
		$em = \XF::em();
		$app = \XF::app();
		$start = microtime(true);

		$output->writeln("Rebuilding style properties...");

		/** @var RebuildService $rebuildService */
		$spRebuildService = $app->service(RebuildService::class);
		$spRebuildService->rebuildFullPropertyMap();
		$spRebuildService->rebuildPropertyStyleCache();

		$output->writeln("Rebuilding template map...");

		/** @var \XF\Service\Template\RebuildService $rebuildService */
		$rebuildService = $app->service(\XF\Service\Template\RebuildService::class);
		$rebuildService->rebuildFullTemplateMap();

		$output->writeln("Recompiling templates...");

		$templateIds = $db->fetchAllColumn("
			SELECT template_id
			FROM xf_template
			ORDER BY template_id
		");

		$outputName = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;

		$progress = new ProgressBar($output, count($templateIds));
		if (!$outputName)
		{
			$progress->start();
		}

		/** @var CompileService $compileService */
		$compileService = $app->service(CompileService::class);

		foreach ($templateIds AS $templateId)
		{
			if (!$outputName)
			{
				$progress->advance();
			}

			/** @var Template $template */
			$template = $em->find(Template::class, $templateId);
			if (!$template)
			{
				continue;
			}

			if ($outputName)
			{
				$output->writeln("$template->type:$template->title");
			}

			$template->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', false);

			$needsSave = $template->reparseTemplate(false);
			if ($needsSave)
			{
				// this will recompile
				$template->save();
			}
			else
			{
				$compileService->recompile($template);
				$compileService->updatePhrasesUsed($template);
			}

			$em->clearEntityCache(); // workaround memory issues
		}

		if (!$outputName)
		{
			$progress->finish();
			$output->writeln("");
		}

		$output->writeln(sprintf("Templates compiled. (%.02fs)", microtime(true) - $start));

		return 0;
	}
}
