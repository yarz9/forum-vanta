<?php

namespace XF\Option;

use XF\Entity\Option;

class ImageLibrary extends AbstractOption
{
	public static function renderOption(Option $option, array $htmlParams)
	{
		return static::getTemplate('admin:option_template_imageLibrary', $option, $htmlParams, [
			'noImagick' => !class_exists('Imagick'),
		]);
	}

	public static function verifyOption(&$value, Option $option)
	{
		if ($value == 'imPecl' && !class_exists('Imagick'))
		{
			$option->error(\XF::phrase('must_have_imagick_pecl_extension', ['link' => 'https://pecl.php.net/package/imagick']), $option->option_id);
			return false;
		}

		return true;
	}
}
