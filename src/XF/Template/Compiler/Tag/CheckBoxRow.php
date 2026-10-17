<?php

namespace XF\Template\Compiler\Tag;

use XF\Template\Compiler;
use XF\Template\Compiler\Syntax\Tag;

class CheckBoxRow extends AbstractFormElement
{
	public function compile(Tag $tag, Compiler $compiler, array $context, $inlineExpected)
	{
		return $this->compileChoiceInput('CheckBox', $tag->name == 'checkboxrow', $tag, $compiler, $context, true);
	}
}
