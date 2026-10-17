<?php

namespace XF\Service\Phrase;

use XF\App;
use XF\Behavior\DevOutputWritable;
use XF\Entity\Language;
use XF\Entity\Phrase;
use XF\Job\Atomic;
use XF\Job\PhraseRebuild;
use XF\Job\TemplateRebuild;
use XF\Service\AbstractService;
use XF\Util\Xml;

class ImportService extends AbstractService
{
	/**
	 * @var Language
	 */
	protected $language;

	public function __construct(App $app, Language $language)
	{
		parent::__construct($app);
		$this->language = $language;
	}

	public function getLanguage()
	{
		return $this->language;
	}

	public function importFromXml(\SimpleXMLElement $container, $addOnId)
	{
		$this->deleteExistingPhrases($addOnId);

		$languageId = $this->language->language_id;
		$existingPhrases = $this->getExistingPhraseMap();

		foreach ($container->phrase AS $xmlPhrase)
		{
			$title = (string) $xmlPhrase['title'];

			$phrase = null;
			if (isset($existingPhrases[$title]))
			{
				$phrase = $this->em()->find(Phrase::class, $existingPhrases[$title]);
			}
			if (!$phrase)
			{
				$phrase = $this->em()->create(Phrase::class);
			}

			$this->setPhraseOptions($phrase);

			$phrase->title = $title;
			$phrase->language_id = $languageId;
			$phrase->phrase_text = Xml::processSimpleXmlCdata((string) $xmlPhrase);
			$phrase->global_cache = (int) $xmlPhrase['global_cache'];
			$phrase->version_id = (int) $xmlPhrase['version_id'];
			$phrase->version_string = (string) $xmlPhrase['version_string'];
			$phrase->addon_id = (string) $xmlPhrase['addon_id'];

			$phrase->save(false, false);
		}

		$this->app->jobManager()->enqueueUnique('languageRebuild', Atomic::class, [
			'execute' => [PhraseRebuild::class, TemplateRebuild::class],
		]);
	}

	protected function deleteExistingPhrases($addOnId)
	{
		$where = 'language_id = ?';
		$params = [$this->language->language_id];

		if ($addOnId)
		{
			$where .= ' AND addon_id = ?';
			$params[] = $addOnId;
		}

		$this->db()->delete('xf_phrase', $where, $params);
	}

	protected function getExistingPhraseMap()
	{
		return $this->db()->fetchPairs("
			SELECT title, phrase_id
			FROM xf_phrase
			WHERE language_id = ?
		", $this->language->language_id);
	}

	protected function setPhraseOptions(Phrase $phrase)
	{
		$phrase->setOption('recompile', false);
		$phrase->setOption('recompile_include', false);
		$phrase->setOption('rebuild_map', false);
		$phrase->setOption('check_duplicate', false);

		$phrase->getBehavior(DevOutputWritable::class)->setOption('write_dev_output', false);
	}
}
