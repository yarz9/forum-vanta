<?php

namespace XF\Import\Data;

/**
 * @mixin \XF\Entity\CaptchaQuestion
 */
class CaptchaQuestion extends AbstractEmulatedData
{
	public function getImportType()
	{
		return 'captcha_question';
	}

	public function getEntityShortName()
	{
		return 'XF:CaptchaQuestion';
	}
}
