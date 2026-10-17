<?php

namespace XF\Alert;

/**
 * @extends AbstractHandler<\XF\Entity\ConversationMessage>
 */
class ConversationMessageHandler extends AbstractHandler
{
	public function getEntityWith()
	{
		return ['Conversation'];
	}

	public function getOptOutActions()
	{
		return ['reaction'];
	}

	public function getOptOutDisplayOrder()
	{
		return 25000;
	}
}
