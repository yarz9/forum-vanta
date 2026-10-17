<?php

namespace XF\Api\Controller;

use XF\Entity\ProfilePost;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Service\ProfilePost\CreatorService;

/**
 * @api-group Profile posts
 */
class ProfilePostsController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('profile_post');
	}

	/**
	 * @api-desc Creates a new profile post.
	 *
	 * @api-in int $user_id <req> The ID of the user whose profile this will be posted on.
	 * @api-in str $message <req>
	 * @api-in str $attachment_key API attachment key to upload files. Attachment key context type must be profile_post with context[profile_user_id] set to this user ID.
	 *
	 * @api-out true $success
	 * @api-out ProfilePost $profile_post
	 */
	public function actionPost(ParameterBag $params)
	{
		$this->assertRequiredApiInput(['user_id', 'message']);
		$this->assertRegisteredUser();

		$userId = $this->filter('user_id', 'uint');

		/** @var User $user */
		$user = $this->assertRecordExists(User::class, $userId);

		if (\XF::isApiCheckingPermissions())
		{
			if (!$user->canViewFullProfile($error) || !$user->canViewPostsOnProfile($error) || !$user->canPostOnProfile())
			{
				throw $this->exception($this->noPermission($error));
			}
		}

		$creator = $this->setupNewProfilePost($user);

		if (\XF::isApiCheckingPermissions())
		{
			$creator->checkForSpam();
		}

		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		/** @var ProfilePost $profilePost */
		$profilePost = $creator->save();
		$this->finalizeNewProfilePost($creator);

		return $this->apiSuccess([
			'profile_post' => $profilePost->toApiResult(Entity::VERBOSITY_VERBOSE),
		]);
	}

	/**
	 * @param User $user
	 *
	 * @return CreatorService
	 */
	protected function setupNewProfilePost(User $user)
	{
		/** @var CreatorService $creator */
		$creator = $this->service(CreatorService::class, $user->Profile);

		$message = $this->filter('message', 'str');
		$creator->setContent($message);

		if (\XF::isApiBypassingPermissions() || $user->canUploadAndManageAttachmentsOnProfile())
		{
			$attachmentKey = $this->filter('attachment_key', 'str');
			$hash = $this->getAttachmentTempHashFromKey(
				$attachmentKey,
				'profile_post',
				['profile_user_id' => $user->user_id]
			);
			$creator->setAttachmentHash($hash);
		}

		return $creator;
	}

	protected function finalizeNewProfilePost(CreatorService $creator)
	{
		$creator->sendNotifications();
	}
}
