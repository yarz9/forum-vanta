<?php

namespace XF\Cli\Command\Designer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Entity\Style;
use XF\Util\File;

class ExportTemplates extends AbstractExportCommand
{
	protected function getContentTypeDetails()
	{
		return [
			'name' => 'templates',
			'command' => 'templates',
			'entity' => 'XF:Template',
		];
	}

	protected function exportData(InputInterface $input, OutputInterface $output, $name, $entityName, Style $style, \Closure $write)
	{
		$ds = \XF::$DS;
		$createDir = \XF::app()->designerOutput()->getDesignerModePath($style->designer_mode)
			. "{$ds}templates{$ds}public";
		File::createDirectory($createDir, false);

		parent::exportData($input, $output, $name, $entityName, $style, $write);
	}
}
