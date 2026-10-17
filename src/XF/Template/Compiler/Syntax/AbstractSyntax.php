<?php

namespace XF\Template\Compiler\Syntax;

use XF\Template\Compiler;
use XF\Template\Compiler\Exception;

abstract class AbstractSyntax
{
	public $line = 0;

	abstract public function compile(Compiler $compiler, array $context, $inlineExpected);

	/**
	 * Set this to false if the value returned can contain an operator.
	 * If false, parentheses will need to be added when used in an operator context.
	 *
	 * @return bool
	 */
	public function isSimpleValue()
	{
		return true;
	}

	public function exception($message)
	{
		return new Exception(\XF::string([
			\XF::phrase('line_x', ['line' => $this->line]), ': ', $message,
		]));
	}
}
