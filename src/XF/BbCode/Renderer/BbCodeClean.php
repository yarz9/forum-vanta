<?php

namespace XF\BbCode\Renderer;

use XF\App;

class BbCodeClean extends AbstractRenderer
{
	public function getCustomTagConfig(array $tag)
	{
		return [];
	}

	public function renderTag(array $tag, array $options)
	{
		return $tag['original'][0]
			. $this->renderSubTree($tag['children'], $options)
			. $tag['original'][1];
	}

	public function renderString($string, array $options)
	{
		return $string;
	}

	public static function factory(App $app)
	{
		return new static();
	}
}
