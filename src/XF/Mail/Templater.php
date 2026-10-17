<?php

namespace XF\Mail;

use XF\App;
use XF\Language;
use XF\Style;

class Templater extends \XF\Template\Templater
{
	public function __construct(App $app, Language $language, $compiledPath)
	{
		parent::__construct($app, $language, $compiledPath);

		// force the router to be the public one by default
		$this->router = $app['router.public'];
	}

	public function setStyle(Style $style)
	{
		parent::setStyle($style);

		if ($style->isVariationsEnabled())
		{
			$style->setVariation(Style::VARIATION_DEFAULT);
		}
	}
}
