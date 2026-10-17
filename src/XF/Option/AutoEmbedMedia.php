<?php

namespace XF\Option;

use XF\Entity\Option;

class AutoEmbedMedia extends AbstractOption
{
	public static function verifyOption(array &$values, Option $option)
	{
		if (empty($values['linkBbCode']))
		{
			$values['linkBbCode'] = '[i][size=2][url={$url}]View: {$url}[/url][/size][/i]';
		}

		if ($values['embedType'])
		{
			if (strpos($values['linkBbCode'], '{$url}') === false)
			{
				$option->error(\XF::phrase('link_bbcode_must_include_url_token'), $option->option_id);
				return false;
			}
		}

		return true;
	}
}
