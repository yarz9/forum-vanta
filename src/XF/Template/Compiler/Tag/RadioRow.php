<?php

namespace XF\Template\Compiler\Tag;

use XF\Template\Compiler;
use XF\Template\Compiler\Syntax\Tag;

class RadioRow extends AbstractFormElement
{
	public function compile(Tag $tag, Compiler $compiler, array $context, $inlineExpected)
	{
		return $this->compileChoiceInput('Radio', $tag->name == 'radiorow', $tag, $compiler, $context, true);
	}
}
