<?php

namespace XF\Service\Widget;

use XF\App;
use XF\Entity\Widget;
use XF\Service\AbstractService;
use XF\Util\File;
use XF\Widget\WidgetCompiler;

class CompileService extends AbstractService
{
	/**
	 * @var Widget
	 */
	protected $widget;

	/**
	 * @var WidgetCompiler
	 */
	protected $compiler;

	public function __construct(App $app, Widget $widget)
	{
		parent::__construct($app);

		$this->widget = $widget;
		$this->compiler = $this->app->widget()->getWidgetCompiler();
	}

	public function compile()
	{
		$widget = $this->widget;
		$compiler = $this->compiler;

		$code = $compiler->compile($widget);
		$contents = "<?php\n\n" . $code;

		$this->writeCode($contents);
	}

	public function writeCode($contents)
	{
		$widgetFile = $this->getWidgetFilename();
		File::writeToAbstractedPath($widgetFile, $contents);
	}

	protected function getWidgetFilename()
	{
		return "code-cache://widgets/_{$this->widget->widget_id}_{$this->widget->widget_key}.php";
	}
}
