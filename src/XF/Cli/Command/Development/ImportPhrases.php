<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Service\Phrase\GroupService;
use XF\Service\Phrase\RebuildService;

class ImportPhrases extends AbstractImportCommand
{
	protected function getContentTypeDetails()
	{
		return [
			'name' => 'phrases',
			'command' => 'phrases',
			'dir' => 'phrases',
			'entity' => 'XF:Phrase',
		];
	}

	protected function getTitleIdMap($typeDir, $addOnId)
	{
		return \XF::db()->fetchPairs("
			SELECT title, phrase_id
			FROM xf_phrase
			WHERE addon_id = ? AND language_id = 0
		", $addOnId);
	}

	public function importData($typeDir, $fileName, $path, $content, $addOnId, array $metadata)
	{
		$title = preg_replace('/\.txt$/', '', $fileName);
		\XF::app()->developmentOutput()->import('XF:Phrase', $title, $addOnId, $content, $metadata, [
			'import' => true,
		]);
		return $title;
	}

	protected function afterExecuteType(array $contentType, InputInterface $input, OutputInterface $output)
	{
		/** @var RebuildService $rebuilder */
		$rebuilder = \XF::app()->service(RebuildService::class);
		$rebuilder->rebuildFullPhraseMap();

		/** @var GroupService $groupService */
		$groupService = \XF::app()->service(GroupService::class);
		$groupService->compileAllPhraseGroups();

		// TODO: how to handle rebuild of templates including phrases?
	}
}
