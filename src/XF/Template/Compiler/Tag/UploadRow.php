<?php

namespace XF\Template\Compiler\Tag;

use XF\Template\Compiler;
use XF\Template\Compiler\Syntax\Tag;

class UploadRow extends AbstractFormElement
{
	public function compile(Tag $tag, Compiler $compiler, array $context, $inlineExpected)
	{
		return $this->compileTextInput('Upload', $tag->name == 'uploadrow', $tag, $compiler, $context);
	}
}
