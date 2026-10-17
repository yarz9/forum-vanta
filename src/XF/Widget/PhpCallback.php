<?php

namespace XF\Widget;

use XF\Http\Request;
use XF\Util\Php;

use function call_user_func_array;

class PhpCallback extends AbstractWidget
{
	public function render()
	{
		$class = $this->options['callback_class'];
		$class = $this->app->extendClass($class);
		$method = $this->options['callback_method'];

		return call_user_func_array([$class, $method], [$this]);
	}

	public function verifyOptions(Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'callback_class' => 'str',
			'callback_method' => 'str',
		]);
		if (!Php::validateCallbackPhrased($options['callback_class'], $options['callback_method'], $error))
		{
			return false;
		}
		return true;
	}
}
