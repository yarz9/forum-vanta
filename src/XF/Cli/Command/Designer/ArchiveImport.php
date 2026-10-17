<?php

namespace XF\Cli\Command\Designer;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use XF\Cli\Command\AbstractCommand;
use XF\Cli\Command\StyleArchiveTrait;
use XF\Repository\StyleRepository;
use XF\Service\Style\ArchiveImportService;
use XF\Service\Style\ImportService;
use XF\Util\Xml;

use function strval;

class ArchiveImport extends AbstractCommand
{
	use RequiresDesignerModeTrait;
	use StyleArchiveTrait;

	protected function configure()
	{
		$this
			->setName('xf-designer:archive-import')
			->setDescription('Overwrites specified designer mode style from a specified style archive path.')
			->addArgument(
				'designer-mode',
				InputArgument::REQUIRED,
				'Designer mode ID'
			)
			->addArgument(
				'archive',
				InputArgument::REQUIRED,
				'Path to valid style archive (Zip).'
			)
			->addOption(
				'force',
				'f',
				InputOption::VALUE_NONE,
				'Skip import checks.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$style = $this->getStyleByDesignerModeInput($input, $output);

		$archiveFile = $input->getArgument('archive');
		if (!is_readable($archiveFile))
		{
			$output->writeln('<error>Unable to read style archive from the specified path. Please try again.</error>');
			return 1;
		}

		if (!\XF::repository(StyleRepository::class)->canSupportStyleArchives())
		{
			$output->writeln('<error>Importing to/exporting from style archives is only supported if you have <code>ZipArchive</code> support. You may need to ask your host to enable this.</error>');
			return 1;
		}

		/** @var ImportService $styleImporter */
		$styleImporter = \XF::service(ImportService::class);

		/** @var ArchiveImportService $styleArchiveImporter */
		$styleArchiveImporter = \XF::service(ArchiveImportService::class, $archiveFile);
		$styleArchiveImporter->setRewriteAssetPaths(false);

		if (!$styleArchiveImporter->validateArchive($errors))
		{
			$output->writeln(array_map('strval', $errors));
			return 1;
		}

		$styleImporter->setArchiveImporter($styleArchiveImporter);

		$xmlFile = $styleArchiveImporter->getXmlFile();

		try
		{
			$document = Xml::openFile($xmlFile);
		}
		catch (\Exception $e)
		{
			$document = null;
		}

		if (!$styleImporter->isValidXml($document, $error))
		{
			$output->writeln(strval($error));
			return 1;
		}

		$styleImporter->setOverwriteStyle($style);
		$output->writeln(["", 'Importing style from archive and overwriting existing style: ' . $style->title]);

		/** @var QuestionHelper $helper */
		$helper = $this->getHelper('question');

		$output->writeln("");

		$question = new ConfirmationQuestion("<question>Are you sure you wish to overwrite the existing designer mode style with the style imported from the archive? (y/n)</question> ");
		if (!$helper->ask($input, $output, $question))
		{
			return 1;
		}

		$output->writeln("");

		if (!$input->getOption('force'))
		{
			if (!$styleImporter->isValidConfiguration($document, $errors))
			{
				$output->writeln('<error>The import could not be completed due to errors: ' . implode(' ', $errors) . ' If you are sure you want to continue try again with the --force option.</error>');
				return 1;
			}
		}

		if (!$styleImporter->validateAssetPathsWritable($document, $failedPaths))
		{
			$output->writeln('<error>The import could not be completed because the following asset paths were not writable: ' . implode(', ', $failedPaths) . '</error>');
			return 1;
		}

		$output->writeln(["", 'Importing style...']);

		$styleImporter->importFromXml($document);

		$output->writeln(["", 'Style import complete.']);

		return 0;
	}
}
