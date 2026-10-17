<?php

namespace XF\BbCode\Renderer;

use XF\App;
use XF\PreEscaped;

class EmailHtml extends Html
{
	public function getDefaultOptions()
	{
		$options = parent::getDefaultOptions();
		$options['smilieFormat'] = 'emoji-only';
		$options['replaceEmojis'] = false;
		$options['noProxy'] = true;
		$options['lightbox'] = false;

		return $options;
	}

	public function getCustomTagConfig(array $tag)
	{
		$output = parent::getCustomTagConfig($tag);

		if ($tag['bb_code_mode'] == 'replace')
		{
			$output['replace'] = $tag['replace_html_email'];
		}

		return $output;
	}

	protected function getRenderedImg($imageUrl, $validUrl, array $params = [])
	{
		$params['imageUrl'] = $imageUrl;
		$params['validUrl'] = $validUrl;

		return $this->templater->renderTemplate('email:bb_code_tag_img', $params);
	}

	protected function getRenderedAttachment($attachment, array $viewParams)
	{
		return $this->templater->renderTemplate('email:bb_code_tag_attach', $viewParams);
	}

	public function renderTagInlineCode(array $children, $option, array $tag, array $options)
	{
		$content = $this->renderSubTree($children, $options);
		return $this->wrapHtml('<code>', $content, '</code>');
	}

	protected function getRenderedCode($content, $language, array $config = [])
	{
		return $this->templater->renderTemplate('email:bb_code_tag_code', [
			'content' => new PreEscaped($content),
			'language' => $language,
		]);
	}

	protected function getRenderedQuote($content, $name, array $source, array $attributes)
	{
		return $this->templater->renderTemplate('email:bb_code_tag_quote', [
			'content' => new PreEscaped($content),
			'name' => $name ? new PreEscaped($name) : null,
			'source' => $source,
			'attributes' => $attributes,
		]);
	}

	protected function getRenderedSpoiler($content, $title = null)
	{
		return $this->templater->renderTemplate('email:bb_code_tag_spoiler', [
			'content' => new PreEscaped($content),
			'title' => $title ? new PreEscaped($title) : null,
		]);
	}

	protected function getRenderedInlineSpoiler($content)
	{
		return $this->templater->renderTemplate('email:bb_code_tag_ispoiler', [
			'content' => new PreEscaped($content),
		]);
	}

	public function renderTagMedia(array $children, $option, array $tag, array $options)
	{
		if (isset($this->mediaSites[strtolower($option)]))
		{
			return $this->templater->renderTemplate('email:bb_code_tag_media');
		}
		else
		{
			return '';
		}
	}

	protected function getRenderedUnfurl($url, array $options)
	{
		$text = $this->prepareTextFromUrlExtended($url, $options);
		return $this->getRenderedLink($text, $url, $options);
	}

	public static function factory(App $app)
	{
		$renderer = parent::factory($app);
		$renderer->setTemplater($app['mailer.templater']);

		return $renderer;
	}
}
