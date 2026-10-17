<?php

namespace XF\Alert;

/**
 * @extends AbstractHandler<\XF\Entity\Thread>
 */
class ThreadHandler extends AbstractHandler
{
	public function getEntityWith()
	{
		$visitor = \XF::visitor();

		return ['Forum', 'Forum.Node.Permissions|' . $visitor->permission_combination_id];
	}
}
