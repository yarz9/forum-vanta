<?php

namespace XF\Pub\View\ServiceWorker;

use XF\CssRenderer;
use XF\Mvc\View;

class Offline extends View
{
	public function renderHtml()
	{
		// clone is important here, as the CSS renderer will manipulate the templater in ways
		// that other code doesn't expect.
		$templater = clone $this->renderer->getTemplater();
		$app = \XF::app();

		$rendererClass = $app->extendClass(CssRenderer::class);

		/** @var CssRenderer $cssRenderer */
		$cssRenderer = new $rendererClass($app, $templater, $app->cache('css'));
		$cssRenderer->setStyle($templater->getStyle());

		$this->params['css'] = $cssRenderer->render($this->params['cssTemplates']);
	}
}
