<?php

namespace XF\Cli\Command\Designer;

use XF\DesignerOutput\Template;
use XF\Entity\Style;

class ImportTemplates extends AbstractImportCommand
{
	protected function getContentTypeDetails()
	{
		return [
			'name' => 'templates',
			'command' => 'templates',
			'dir' => 'templates',
			'entity' => 'XF:Template',
		];
	}

	protected function getTitleIdMap($typeDir, $styleId)
	{
		return \XF::db()->fetchPairs("
			SELECT CONCAT(type, '/', title), template_id
			FROM xf_template
			WHERE style_id = ?
		", $styleId);
	}

	public function importData($typeDir, $fileName, $path, $content, Style $style, array $metadata)
	{
		/** @var Template $designerOutputHandler */
		$designerOutputHandler = \XF::app()->designerOutput()->getHandler('XF:Template');
		$title = $designerOutputHandler->convertTemplateFileToName($fileName);
		$template = $designerOutputHandler->import($title, $style->style_id, $content, $metadata, [
			'import' => true,
		]);
		return "$template->type/$template->title";
	}
}
