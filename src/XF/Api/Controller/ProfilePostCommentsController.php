<?php

namespace XF\Api\Controller;

use XF\Entity\ProfilePost;
use XF\Entity\ProfilePostComment;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Service\ProfilePostComment\CreatorService;

/**
 * @api-group Profile posts
 */
class ProfilePostCommentsController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('profile_post');
	}

	/**
	 * @api-desc Creates a new profile post comment.
	 *
	 * @api-in int $profile_post_id <req> The ID of the profile post this comment will be attached to.
	 * @api-in str $message <req>
	 * @api-in str $attachment_key API attachment key to upload files. Attachment key context type must be profile_post_comment with context[profile_post_id] set to this profile post ID.
	 *
	 * @api-out true $success
	 * @api-out ProfilePostComment $comment
	 */
	public function actionPost(ParameterBag $params)
	{
		$this->assertRequiredApiInput(['profile_post_id', 'message']);
		$this->assertRegisteredUser();

		$profilePostId = $this->filter('profile_post_id', 'uint');

		/** @var ProfilePost $profilePost */
		$profilePost = $this->assertViewableApiRecord(ProfilePost::class, $profilePostId);

		if (\XF::isApiCheckingPermissions() && !$profilePost->canComment($error))
		{
			return $this->noPermission($error);
		}

		$creator = $this->setupNewProfilePostComment($profilePost);

		if (\XF::isApiCheckingPermissions())
		{
			$creator->checkForSpam();
		}

		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		/** @var ProfilePostComment $comment */
		$comment = $creator->save();
		$this->finalizeNewProfilePostComment($creator);

		return $this->apiSuccess([
			'comment' => $comment->toApiResult(Entity::VERBOSITY_VERBOSE),
		]);
	}

	/**
	 * @param ProfilePost $profilePost
	 *
	 * @return CreatorService
	 */
	protected function setupNewProfilePostComment(ProfilePost $profilePost)
	{
		/** @var CreatorService $creator */
		$creator = $this->service(CreatorService::class, $profilePost);

		$message = $this->filter('message', 'str');
		$creator->setContent($message);

		if (\XF::isApiBypassingPermissions() || $profilePost->canUploadAndManageAttachments())
		{
			$attachmentKey = $this->filter('attachment_key', 'str');
			$hash = $this->getAttachmentTempHashFromKey(
				$attachmentKey,
				'profile_post_comment',
				['profile_post_id' => $profilePost->profile_post_id]
			);
			$creator->setAttachmentHash($hash);
		}

		return $creator;
	}

	protected function finalizeNewProfilePostComment(CreatorService $creator)
	{
		$creator->sendNotifications();
	}
}
