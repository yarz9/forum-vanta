<?php

namespace XF\Search\Source;

use XF\Job\SearchUserChange;
use XF\Search\IndexRecord;
use XF\Search\Query;
use XF\Search\Query\KeywordQuery;
use XF\Util\Arr;
use XF\Util\Str;

use function count, in_array, strlen;

abstract class AbstractSource
{
	/**
	 * @var int
	 */
	public const DEFAULT_MAX_KEYWORDS = 1024;

	/**
	 * @var bool
	 */
	protected $bulkIndexing = false;

	/**
	 * @return bool
	 */
	abstract public function isRelevanceSupported();

	abstract public function index(IndexRecord $record);

	abstract protected function flushBulkIndexing();

	/**
	 * @param string $type
	 * @param list<int>|int $ids
	 */
	abstract public function delete($type, $ids);

	/**
	 * @param string|null $type
	 */
	abstract public function truncate($type = null);

	/**
	 * @param int $maxResults
	 *
	 * @return list<array{content_type: string, content_result: int}>
	 */
	abstract public function search(KeywordQuery $query, $maxResults);

	/**
	 * @param list<string> $parsed
	 *
	 * @return string
	 */
	abstract protected function finalizeParsedKeywords(array $parsed);

	public function isAutoCompleteSupported(): bool
	{
		return false;
	}

	/**
	 * @return list<array{content_type: string, content_result: int}>
	 */
	public function autoComplete(
		KeywordQuery $query,
		int $maxResults
	): array
	{
		return [];
	}

	/**
	 * @param KeywordQuery $query
	 * @param string|null $error
	 *
	 * @return bool
	 */
	public function isQueryEmpty(Query\Query $query, &$error = null)
	{
		if (!strlen($query->getKeywords()) && !$query->getUserIds())
		{
			$error = \XF::phrase('please_specify_search_query_or_name_of_member');
			return true;
		}

		return false;
	}

	public function enableBulkIndexing()
	{
		$this->bulkIndexing = true;
	}

	public function disableBulkIndexing()
	{
		if ($this->bulkIndexing)
		{
			$this->flushBulkIndexing();
		}

		$this->bulkIndexing = false;
	}

	/**
	 * @param int $oldUserId
	 * @param int $newUserId
	 */
	public function reassignContent($oldUserId, $newUserId)
	{
		\XF::app()->jobManager()->enqueue(SearchUserChange::class, ['user_id' => $oldUserId]);
	}

	public function isValidKeyword(string $word): bool
	{
		if (Str::strlen($word) < $this->getMinWordLength())
		{
			return false;
		}

		if (in_array($word, $this->getStopWords(), true))
		{
			return false;
		}

		return true;
	}

	/**
	 * @return list<string>
	 */
	public function getStopWords()
	{
		return [];
	}

	/**
	 * @return int
	 */
	public function getMinWordLength()
	{
		return 1;
	}

	/**
	 * @return string
	 */
	public function getWordSplitRange()
	{
		return '\x00-\x21\x28\x29\x2C-\x2F\x3A-\x40\x5B-\x5E\x60\x7B\x7D-\x7F';
	}

	public function getMaxKeywords(): int
	{
		return self::DEFAULT_MAX_KEYWORDS;
	}

	/**
	 * @param string $keywords
	 * @param string|null $error
	 * @param string|null $warning
	 *
	 * @return string
	 */
	public function parseKeywords($keywords, &$error = null, &$warning = null)
	{
		$output = [];
		$i = 0;

		$haveWords = false;
		$invalidWords = [];

		$splitRange = $this->getWordSplitRange();
		$minWordLength = $this->getMinWordLength();
		$stopWords = $this->getStopWords();

		foreach ($this->tokenizeKeywords($keywords) AS $match)
		{
			$haveTermWords = false;
			$invalidTermWords = [];

			$modifier = $match['modifier'];
			if ($modifier === '|' && $i === 0)
			{
				$modifier = '';
			}

			$quoted = !empty($match['quoteTerm']);
			$term = $quoted ? $match['quoteTerm'] : $match['term'];
			if ($quoted)
			{
				$term = preg_replace('/[' . $splitRange . ']/', ' ', $term);
			}
			else
			{
				$term = str_replace('"', ' ', $term); // unmatched quotes
				$term = preg_replace('/^(AND|OR|NOT)$/', '', $term); // words may have special meaning
			}

			$term = trim($term);

			foreach ($this->splitWords($term) AS $word)
			{
				if ($word === '')
				{
					continue;
				}

				if (!$this->isValidKeyword($word))
				{
					$invalidTermWords[] = $word;
				}
				else
				{
					$haveTermWords = true;
				}
			}

			if (!$haveTermWords && !$invalidTermWords)
			{
				$invalidTermWords[] = $match['term'];
			}

			$invalidWords = array_merge($invalidWords, $invalidTermWords);

			if (!$haveTermWords)
			{
				continue;
			}

			$haveWords = true;

			if ($modifier === '|' && $i > 0 && $output[$i - 1][0] === '')
			{
				$output[$i - 1][0] = '|';
			}

			$output[$i] = [$modifier, ($quoted ? "\"$term\"" : $term)];
			$i++;
		}

		$error = null;
		$warning = null;

		if ($invalidWords)
		{
			if ($haveWords)
			{
				$warning = \XF::phrase(
					'following_words_were_not_included_in_your_search_x',
					['words' => implode(', ', $invalidWords)]
				)->render('raw');
			}
			else
			{
				$error = \XF::phrase(
					'search_could_not_be_completed_because_search_keywords_were_too'
				);
			}
		}

		$keywordCount = count($output);
		$maxKeywords = $this->getMaxKeywords();
		if ($maxKeywords && $keywordCount > $maxKeywords)
		{
			$error = \XF::phrase(
				'search_could_not_be_completed_because_number_of_keywords_exceeds_limit_x',
				['maxKeywords' => \XF::language()->numberFormat($maxKeywords)]
			);
		}

		return $this->finalizeParsedKeywords($output);
	}

	/**
	 * @param string $keywords
	 *
	 * @return array{modifier: string, term: string, quoteTerm: string}
	 */
	protected function tokenizeKeywords($keywords)
	{
		$keywords = str_replace(['(', ')'], '', trim($keywords)); // don't support grouping yet

		$splitRange = $this->getWordSplitRange();
		preg_match_all('/
			(?<=[' . $splitRange . '\-\+\|]|^)
			(?P<modifier>
				  (?<!\-|\+|\|)\-
				| (?<!\-|\+|\|)\+
				| (?<!\-|\+|\|)\|\s+
				|
			)
			(?P<term>"(?P<quoteTerm>[^"]+)"|[^' . $splitRange . '\-\+\|]+)
		/ix', $keywords, $matches, PREG_SET_ORDER);

		foreach ($matches AS &$match)
		{
			if ($match['modifier'])
			{
				$match['modifier'] = trim($match['modifier']);
				$match[1] = trim($match[1]);
			}
		}

		return $matches;
	}

	/**
	 * @param string $words
	 *
	 * @return list<string>
	 */
	protected function splitWords($words)
	{
		return Arr::stringToArray($words, '/[' . $this->getWordSplitRange() . ']/');
	}
}
