<?php

namespace XF\Search\Query;

class KeywordQuery extends Query
{
	/**
	 * @var string
	 */
	protected $keywords = '';

	/**
	 * @var string
	 */
	protected $parsedKeywords = null;

	/**
	 * @var bool
	 */
	protected $titleOnly = false;

	/**
	 * @param string $keywords
	 * @param bool $titleOnly
	 *
	 * @return $this
	 */
	public function withKeywords($keywords, $titleOnly = false)
	{
		$this->keywords = trim($keywords);
		$this->parsedKeywords = $this->search->getParsedKeywords($this->keywords, $error, $warning);
		$this->titleOnly = (bool) $titleOnly;

		if ($error)
		{
			$this->error('keywords', $error);
		}
		if ($warning)
		{
			$this->warning('keywords', $warning);
		}

		return $this;
	}

	/**
	 * @return string
	 */
	public function getKeywords()
	{
		return $this->keywords;
	}

	/**
	 * @return string
	 */
	public function getParsedKeywords()
	{
		return $this->parsedKeywords;
	}

	/**
	 * @param bool $titleOnly
	 *
	 * @return $this
	 */
	public function inTitleOnly($titleOnly = true)
	{
		$this->titleOnly = (bool) $titleOnly;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function getTitleOnly()
	{
		return $this->titleOnly;
	}

	/**
	 * @return array{keywords: string, titleOnly: bool}
	 */
	public function getUniqueQueryComponents()
	{
		return [
			'keywords' => $this->keywords,
			'titleOnly' => $this->titleOnly,
		];
	}
}
