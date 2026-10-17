<?php

namespace XF\EmbedResolver;

class PostHandler extends AbstractHandler
{
	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();

		return ['Thread', 'Thread.Forum', 'Thread.Forum.Node.Permissions|' . $visitor->permission_combination_id];
	}
}
