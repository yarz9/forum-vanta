<?php

namespace XF\Repository;

use XF\Entity\Language;
use XF\Entity\Phrase;
use XF\Finder\PhraseMapFinder;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;

use function count;

class PhraseRepository extends Repository
{
	/**
	 * @param Language $language
	 *
	 * @return PhraseMapFinder
	 */
	public function findEffectivePhrasesInLanguage(Language $language)
	{
		/** @var PhraseMapFinder $finder */
		$finder = $this->finder(PhraseMapFinder::class);
		$finder
			->where('language_id', $language->language_id)
			->with('Phrase', true)
			->orderTitle()
			->pluckFrom('Phrase', 'phrase_id');

		return $finder;
	}

	/**
	 * @param Language $language
	 * @param $title
	 *
	 * @return Phrase
	 */
	public function getEffectivePhraseByTitle(Language $language, $title)
	{
		$finder = $this->finder(PhraseMapFinder::class);
		return $finder
			->where('language_id', $language->language_id)
			->where('title', $title)
			->pluckFrom('Phrase', 'phrase_id')
			->fetchOne();
	}

	/**
	 * @param Language $language
	 * @param array $titles
	 *
	 * @return ArrayCollection|Phrase[]
	 */
	public function getEffectivePhrasesByTitles(Language $language, array $titles)
	{
		$finder = $this->finder(PhraseMapFinder::class);
		return $finder
			->where('language_id', $language->language_id)
			->where('title', $titles)
			->pluckFrom('Phrase', 'title')
			->fetch();
	}

	public function countOutdatedPhrases()
	{
		return count($this->getBaseOutdatedPhraseData());
	}

	public function getOutdatedPhrases()
	{
		$data = $this->getBaseOutdatedPhraseData();
		$phraseIds = array_keys($data);

		if (!$phraseIds)
		{
			return [];
		}

		$phrases = $this->em->findByIds(Phrase::class, $phraseIds);

		$output = [];
		foreach ($data AS $phraseId => $outdated)
		{
			if (!isset($phrases[$phraseId]))
			{
				continue;
			}

			$outdated['phrase'] = $phrases[$phraseId];
			$output[$phraseId] = $outdated;
		}

		return $output;
	}

	protected function getBaseOutdatedPhraseData()
	{
		$db = $this->db();

		return $db->fetchAllKeyed('
			SELECT phrase.phrase_id,
				parent.version_string AS parent_version_string
			FROM xf_phrase AS phrase
			INNER JOIN xf_language AS language ON (language.language_id = phrase.language_id)
			INNER JOIN xf_phrase_map AS map ON (map.language_id = language.parent_id AND map.title = phrase.title)
			INNER JOIN xf_phrase AS parent ON (map.phrase_id = parent.phrase_id AND parent.version_id > phrase.version_id)
			WHERE phrase.language_id > 0
			ORDER BY phrase.title
		', 'phrase_id');
	}

	public function quickCustomizePhrase(Language $language, $title, $text, array $extra = [])
	{
		$existingPhrase = $this->getEffectivePhraseByTitle($language, $title);
		if (!$existingPhrase)
		{
			// first time this phrase exists
			$phrase = $this->em->create(Phrase::class);
			$phrase->language_id = $language->language_id;
			$phrase->title = $title;
			$phrase->addon_id = ''; // very likey to be correct, can be overridden if needed
		}
		else if ($existingPhrase->language_id != $language->language_id)
		{
			// phrase exists in a parent
			$phrase = $this->em->create(Phrase::class);
			$phrase->language_id = $language->language_id;
			$phrase->title = $title;
			$phrase->addon_id = $existingPhrase->addon_id;
		}
		else
		{
			// phrase already exists in this language
			$phrase = $existingPhrase;
		}

		$phrase->phrase_text = $text;
		$phrase->bulkSet($extra);
		$phrase->save();

		return $phrase;
	}
}
