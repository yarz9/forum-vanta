<?php

namespace XF\Cli\Command\Rebuild;

use XF\Job\AttachmentThumb;

class RebuildAttachmentThumbs extends AbstractRebuildCommand
{
	protected function getRebuildName(): string
	{
		return 'attachment-thumbnails';
	}

	protected function getRebuildDescription(): string
	{
		return 'Rebuilds attachment thumbnails';
	}

	protected function getRebuildClass(): string
	{
		return AttachmentThumb::class;
	}
}
