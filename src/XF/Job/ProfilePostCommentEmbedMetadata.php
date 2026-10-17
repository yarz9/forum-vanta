<?php

namespace XF\Job;

use XF\Entity\ProfilePostComment;
use XF\Mvc\Entity\Entity;
use XF\Service\Message\PreparerService;

class ProfilePostCommentEmbedMetadata extends AbstractEmbedMetadataJob
{
	protected function getIdsToRebuild(array $types)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn(
			$db->limit(
				'SELECT profile_post_comment_id
					FROM xf_profile_post_comment
					WHERE profile_post_comment_id > ?
					ORDER BY profile_post_comment_id',
				$this->data['batch']
			),
			$this->data['start']
		);
	}

	protected function getRecordToRebuild($id)
	{
		return $this->app->em()->find(ProfilePostComment::class, $id);
	}

	protected function getPreparerContext()
	{
		return 'profile_post_comment';
	}

	protected function getMessageContent(Entity $record)
	{
		return $record->message;
	}

	protected function rebuildQuotes(Entity $record, PreparerService $preparer, array &$embedMetadata): void
	{
		$embedMetadata['quotes'] = $preparer->getEmbeddedQuotes();
	}

	protected function rebuildAttachments(Entity $record, PreparerService $preparer, array &$embedMetadata)
	{
		$embedMetadata['attachments'] = $preparer->getEmbeddedAttachments();
	}

	protected function rebuildEmbeds(Entity $record, PreparerService $preparer, array &$embedMetadata): void
	{
		$embedMetadata['embeds'] = $preparer->getEmbeds();
	}

	protected function rebuildImages(Entity $record, PreparerService $preparer, array &$embedMetadata): void
	{
		$embedMetadata['images'] = $preparer->getEmbeddedImages();
	}

	protected function getActionDescription()
	{
		$rebuildPhrase = \XF::phrase('rebuilding');
		$type = \XF::phrase('profile_post_comments');
		return sprintf('%s... %s', $rebuildPhrase, $type);
	}
}
