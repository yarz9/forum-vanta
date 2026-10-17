<?php

namespace XF\Job;

use XF\Entity\ProfilePost;
use XF\Mvc\Entity\Entity;
use XF\Service\Message\PreparerService;

class ProfilePostEmbedMetadata extends AbstractEmbedMetadataJob
{
	protected function getIdsToRebuild(array $types): array
	{
		$db = $this->app->db();

		// Note: we can no longer use the getIdsBug153298Workaround() function because other embed
		// types are supported.
		return $db->fetchAllColumn($db->limit(
			"
				SELECT profile_post_id
				FROM xf_profile_post
				WHERE profile_post_id > ?
				ORDER BY profile_post_id
			",
			$this->data['batch']
		), $this->data['start']);
	}

	protected function getRecordToRebuild($id)
	{
		return $this->app->em()->find(ProfilePost::class, $id);
	}

	protected function getPreparerContext(): string
	{
		return 'profile_post';
	}

	protected function getMessageContent(Entity $record)
	{
		return $record->message;
	}

	protected function rebuildQuotes(Entity $record, PreparerService $preparer, array &$embedMetadata): void
	{
		$embedMetadata['quotes'] = $preparer->getEmbeddedQuotes();
	}

	protected function rebuildAttachments(Entity $record, PreparerService $preparer, array &$embedMetadata): void
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

	protected function getActionDescription(): string
	{
		$rebuildPhrase = \XF::phrase('rebuilding');
		$type = \XF::phrase('profile_posts');
		return sprintf('%s... %s', $rebuildPhrase, $type);
	}
}
