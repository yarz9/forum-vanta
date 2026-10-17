<?php

namespace XF\Template\Compiler\Tag;

use XF\Template\Compiler;
use XF\Template\Compiler\Syntax\Tag;

class Captcha extends AbstractTag
{
	public function compile(Tag $tag, Compiler $compiler, array $context, $inlineExpected)
	{
		$tag->assertEmpty();

		$config = $this->compileAttributesAsArray($tag->attributes, $compiler, $context);
		$indent = $compiler->indent();
		$optionsCode = "array(array(" . implode('', $config) . "\n$indent))";

		return "{$compiler->templaterVariable}->func('captcha_options', $optionsCode)";
	}
}
