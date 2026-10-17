<?php

namespace XF\Api\Controller;

use XF\Api\ControllerPlugin\ReactionPlugin;
use XF\Entity\ProfilePostComment;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Service\ProfilePostComment\DeleterService;
use XF\Service\ProfilePostComment\EditorService;

/**
 * @api-group Profile posts
 */
class ProfilePostCommentController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('profile_post');
	}

	/**
	 * @api-desc Gets information about the specified profile post comment.
	 *
	 * @api-out ProfilePostComment $comment
	 */
	public function actionGet(ParameterBag $params)
	{
		$comment = $this->assertViewableProfilePostComment($params->profile_post_comment_id, 'api|post');

		$result = $comment->toApiResult(Entity::VERBOSITY_VERBOSE, [
			'with_post' => true,
		]);

		return $this->apiResult(['comment' => $result]);
	}

	/**
	 * @api-desc Updates the specified profile post comment.
	 *
	 * @api-in str $message
	 * @api-in bool $author_alert
	 * @api-in str $author_alert_reason
	 * @api-in str $attachment_key API attachment key to upload files. Attachment key context type must be profile_post_comment with context[profile_post_comment_id] set to this profile post comment ID.
	 *
	 * @api-out true $success
	 * @api-out ProfilePostComment $comment
	 */
	public function actionPost(ParameterBag $params)
	{
		$comment = $this->assertViewableProfilePostComment($params->profile_post_comment_id);

		if (\XF::isApiCheckingPermissions() && !$comment->canEdit($error))
		{
			return $this->noPermission($error);
		}

		$editor = $this->setupProfilePostCommentEdit($comment);

		if (\XF::isApiCheckingPermissions())
		{
			$editor->checkForSpam();
		}

		if (!$editor->validate($errors))
		{
			return $this->error($errors);
		}

		$editor->save();

		return $this->apiSuccess([
			'comment' => $comment->toApiResult(Entity::VERBOSITY_VERBOSE),
		]);
	}

	/**
	 * @param ProfilePostComment $comment
	 *
	 * @return EditorService
	 */
	protected function setupProfilePostCommentEdit(ProfilePostComment $comment)
	{
		$input = $this->filter([
			'message' => '?str',
			'author_alert' => 'bool',
			'author_alert_reason' => 'str',
			'attachment_key' => 'str',
		]);

		/** @var EditorService $editor */
		$editor = $this->service(EditorService::class, $comment);

		if ($input['message'] !== null)
		{
			$editor->setMessage($input['message']);
		}

		if (\XF::isApiBypassingPermissions() || $comment->ProfilePost->canUploadAndManageAttachments())
		{
			$hash = $this->getAttachmentTempHashFromKey(
				$input['attachment_key'],
				'profile_post_comment',
				['profile_post_comment_id' => $comment->profile_post_comment_id]
			);
			$editor->setAttachmentHash($hash);
		}

		if ($input['author_alert'] && $comment->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $input['author_alert_reason']);
		}

		return $editor;
	}

	/**
	 * @api-desc Deletes the specified profile post comment. Default to soft deletion.
	 *
	 * @api-in bool $hard_delete
	 * @api-in str $reason
	 * @api-in bool $author_alert
	 * @api-in str $author_alert_reason
	 *
	 * @api-out true $success
	 */
	public function actionDelete(ParameterBag $params)
	{
		$comment = $this->assertViewableProfilePostComment($params->profile_post_comment_id);

		if (\XF::isApiCheckingPermissions() && !$comment->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		$type = 'soft';
		$reason = $this->filter('reason', 'str');

		if ($this->filter('hard_delete', 'bool'))
		{
			$this->assertApiScope('profile_post:delete_hard');

			if (\XF::isApiCheckingPermissions() && !$comment->canDelete('hard', $error))
			{
				return $this->noPermission($error);
			}

			$type = 'hard';
		}

		/** @var DeleterService $deleter */
		$deleter = $this->service(DeleterService::class, $comment);

		if ($this->filter('author_alert', 'bool') && $comment->canSendModeratorActionAlert())
		{
			$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		$deleter->delete($type, $reason);

		return $this->apiSuccess();
	}

	/**
	 * @api-desc Reacts to the specified profile post comment
	 *
	 * @api-see \XF\Api\ControllerPlugin\Reaction::actionReact()
	 */
	public function actionPostReact(ParameterBag $params)
	{
		$comment = $this->assertViewableProfilePostComment($params->profile_post_comment_id);

		/** @var ReactionPlugin $reactPlugin */
		$reactPlugin = $this->plugin(ReactionPlugin::class);
		return $reactPlugin->actionReact($comment);
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return ProfilePostComment
	 *
	 * @throws Exception
	 */
	protected function assertViewableProfilePostComment($id, $with = 'api')
	{
		return $this->assertViewableApiRecord(ProfilePostComment::class, $id, $with);
	}
}
