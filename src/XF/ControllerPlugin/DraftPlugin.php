<?php

namespace XF\ControllerPlugin;

use XF\Draft;
use XF\Mvc\Reply\AbstractReply;

use function is_array, strlen;

class DraftPlugin extends AbstractPlugin
{
	public function actionDraftMessage(
		Draft $draft,
		array $extraData = [],
		$messageInput = 'message',
		&$actionTaken = null
	)
	{
		$actionTaken = $this->updateMessageDraft($draft, $extraData, $messageInput);
		return $this->getDraftReply($actionTaken);
	}

	public function updateMessageDraft(Draft $draft, array $extraData = [], $messageInput = 'message')
	{
		$message = $this->controller->plugin(EditorPlugin::class)->fromInput($messageInput);

		if ($this->request->filter('delete', 'bool') || !strlen($message))
		{
			$draft->delete();

			return 'delete';
		}
		else
		{
			$draft->message = $message;
			$draft->extra_data = $extraData;
			$draft->save();

			return 'save';
		}
	}

	public function actionDraftMessageless(Draft $draft, array $extraData, &$actionTaken = null)
	{
		$actionTaken = $this->updateMessagelessDraft($draft, $extraData);
		return $this->getDraftReply($actionTaken);
	}

	public function updateMessagelessDraft(Draft $draft, array $extraData)
	{
		if ($this->request->filter('delete', 'bool'))
		{
			$draft->delete();

			return 'delete';
		}
		else
		{
			$draft->message = '';
			$draft->extra_data = $extraData;
			$draft->save();

			return 'save';
		}
	}

	public function getDraftReply($actionTaken)
	{
		if ($actionTaken == 'delete')
		{
			$message = \XF::phrase('draft_deleted_successfully');
		}
		else
		{
			$message = \XF::phrase('draft_saved_successfully');
		}

		$reply = $this->message($message);
		$this->addDraftJsonParams($reply, $actionTaken);

		return $reply;
	}

	public function addDraftJsonParams(AbstractReply $reply, $action)
	{
		if ($action == 'delete')
		{
			$message = \XF::phrase('draft_deleted_successfully');
		}
		else
		{
			$message = \XF::phrase('draft_saved_successfully');
		}

		$reply->setJsonParam('draft', [
			'action' => $action,
			'message' => $message,
			'saved' => true,
		]);
	}

	public function refreshTempAttachments($attachmentHashes)
	{
		if (!is_array($attachmentHashes))
		{
			$attachmentHashes = [$attachmentHashes];
		}

		$attachmentHashes = array_filter($attachmentHashes);

		if ($attachmentHashes)
		{
			$db = $this->app->db();
			$db->update('xf_attachment', ['attach_date' => \XF::$time], 'temp_hash IN (' . $db->quote($attachmentHashes) . ')');
		}
	}
}
