<?php

namespace XF\Import\Data;

/**
 * @mixin \XF\Entity\ThreadReplyBan
 */
class ThreadReplyBan extends AbstractEmulatedData
{
	public function getImportType()
	{
		return 'thread_reply_ban';
	}

	public function getEntityShortName()
	{
		return 'XF:ThreadReplyBan';
	}
}
