<?php

namespace XF\Template\Compiler\Tag;

use XF\Template\Compiler;
use XF\Template\Compiler\Syntax\Tag;

class DateInputRow extends AbstractFormElement
{
	public function compile(Tag $tag, Compiler $compiler, array $context, $inlineExpected)
	{
		return $this->compileTextInput('DateInput', $tag->name == 'dateinputrow', $tag, $compiler, $context);
	}
}
