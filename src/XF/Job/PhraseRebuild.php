<?php

namespace XF\Job;

use XF\Behavior\DevOutputWritable;
use XF\Entity\Phrase;
use XF\Repository\LanguageRepository;
use XF\Service\Phrase\CompileService;
use XF\Service\Phrase\GroupService;
use XF\Service\Phrase\RebuildService;

class PhraseRebuild extends AbstractJob
{
	protected $defaultData = [
		'steps' => 0,
		'phraseId' => 0,
		'batch' => 5000,
		'mapped' => false,
		'skipCore' => false,
	];

	public function run($maxRunTime)
	{
		$start = microtime(true);

		if (!$this->data['mapped'])
		{
			/** @var RebuildService $rebuildService */
			$rebuildService = $this->app->service(RebuildService::class);
			$rebuildService->rebuildFullPhraseMap();

			/** @var GroupService $groupService */
			$groupService = $this->app->service(GroupService::class);
			$groupService->compileAllPhraseGroups();

			$this->data['mapped'] = true;
		}

		$this->data['steps']++;

		$db = $this->app->db();
		$em = $this->app->em();
		$app = \XF::app();

		if ($this->data['skipCore'])
		{
			$skipCoreSql = "AND (addon_id <> 'XF' OR language_id > 0)";
		}
		else
		{
			$skipCoreSql = '';
		}

		$phraseIds = $db->fetchAllColumn($db->limit(
			"
				SELECT phrase_id
				FROM xf_phrase
				WHERE phrase_id > ?
					{$skipCoreSql}
				ORDER BY phrase_id
			",
			$this->data['batch']
		), $this->data['phraseId']);
		if (!$phraseIds)
		{
			$app->repository(LanguageRepository::class)->rebuildLanguageCache();

			return $this->complete();
		}

		/** @var CompileService $compileService */
		$compileService = $app->service(CompileService::class);

		$done = 0;

		foreach ($phraseIds AS $phraseId)
		{
			$this->data['phraseId'] = $phraseId;

			/** @var Phrase $phrase */
			$phrase = $em->find(Phrase::class, $phraseId);
			if (!$phrase)
			{
				continue;
			}

			$phrase->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', false);

			$compileService->recompile($phrase);

			$done++;

			if (microtime(true) - $start >= $maxRunTime)
			{
				break;
			}
		}

		// decache to reduce memory usage
		\XF::em()->clearEntityCache(Phrase::class);

		$this->data['batch'] = $this->calculateOptimalBatch($this->data['batch'], $done, $start, $maxRunTime, 5000);

		return $this->resume();
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('rebuilding');
		$typePhrase = \XF::phrase('phrases');
		return sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat('. ', $this->data['steps']));
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}
