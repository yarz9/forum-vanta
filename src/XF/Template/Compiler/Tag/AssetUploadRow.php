<?php

namespace XF\Template\Compiler\Tag;

use XF\Template\Compiler;
use XF\Template\Compiler\Syntax\Tag;

class AssetUploadRow extends AbstractFormElement
{
	public function compile(Tag $tag, Compiler $compiler, array $context, $inlineExpected)
	{
		$tag->assertAttribute('asset');

		return $this->compileTextInput('AssetUpload', $tag->name == 'assetuploadrow', $tag, $compiler, $context);
	}
}
