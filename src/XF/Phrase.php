<?php

namespace XF;

use function strval;

class Phrase implements \JsonSerializable
{
	/**
	 * @var Language
	 */
	protected $language;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var array<string, string|int|float>
	 */
	protected $params;

	/**
	 * @var bool
	 */
	protected $allowHtml = true;

	/**
	 * @var array{fallback?: string|null, fallbackRaw?: bool, nameOnInvalid?: bool}
	 */
	protected $options = [];

	/**
	 * @param string $name
	 * @param array<string, string|int|float> $params
	 * @param bool $allowHtml
	 */
	public function __construct(Language $language, $name, array $params = [], $allowHtml = true)
	{
		$this->language = $language;
		$this->name = $name;
		$this->params = $params;
		$this->allowHtml = $allowHtml;
	}

	/**
	 * @param bool $allowHtml
	 *
	 * @return $this
	 */
	public function allowHtml($allowHtml)
	{
		$this->allowHtml = $allowHtml;

		return $this;
	}

	/**
	 * @param string|null $fallback
	 * @param bool $raw
	 *
	 * @return $this
	 */
	public function fallback($fallback, $raw = false)
	{
		$this->options['fallback'] = $fallback;
		$this->options['fallbackRaw'] = $raw;

		return $this;
	}

	/**
	 * @param 'html'|'raw'|'js'|'json'|'htmljs'|'datauri' $context
	 * @param array{fallback?: string|null, fallbackRaw?: bool, nameOnInvalid?: bool} $options
	 *
	 * @return string
	 */
	public function render($context = 'html', array $options = [])
	{
		$options = array_replace($this->options, $options);

		if (!$this->allowHtml && $context == 'html')
		{
			$output = $this->language->renderPhrase($this->name, $this->params, 'raw', $options);
			return \XF::escapeString($output, 'html');
		}

		return $this->language->renderPhrase($this->name, $this->params, $context, $options);
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		try
		{
			return strval($this->render());
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Phrase rendering error: ');
			return htmlspecialchars($this->name);
		}
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @return array<string, string|int|float>
	 */
	public function getParams()
	{
		return $this->params;
	}

	/**
	 * @param array<string, string|int|float>
	 */
	public function setParams(array $params)
	{
		$this->params = $params;
	}

	public function jsonSerialize(): string
	{
		return $this->__toString();
	}
}
