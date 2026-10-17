<?php

namespace XF\Template\Compiler\Tag;

use XF\Template\Compiler;
use XF\Template\Compiler\Syntax\Tag;

class DateTimeInputRow extends AbstractFormElement
{
	public function compile(Tag $tag, Compiler $compiler, array $context, $inlineExpected)
	{
		return $this->compileTextInput('DateTimeInput', $tag->name == 'datetimeinputrow', $tag, $compiler, $context);
	}
}
