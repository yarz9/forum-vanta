<?php

namespace XF\EmbedResolver;

class ThreadHandler extends AbstractHandler
{
	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();

		return ['Forum', 'Forum.Node.Permissions|' . $visitor->permission_combination_id, 'FirstPost', 'User'];
	}
}
