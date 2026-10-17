<?php

namespace XF\Api\Controller;

use XF\ControllerPlugin\AttachmentPlugin;
use XF\Entity\ApiAttachmentKey;
use XF\Entity\Attachment;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Repository\AttachmentRepository;

/**
 * @api-group Attachments
 */
class AttachmentController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('attachment', ['delete' => 'delete']);
	}

	/**
	 * @api-desc Gets information about the specified attachment.
	 *
	 * @api-out Attachment $attachment
	 */
	public function actionGet(ParameterBag $params)
	{
		$attachment = $this->assertViewableAttachment($params->attachment_id);

		$result = $attachment->toApiResult(Entity::VERBOSITY_VERBOSE);

		return $this->apiResult(['attachment' => $result]);
	}

	/**
	 * @api-desc Gets the data that makes up the specified attachment. The output is the raw binary data.
	 *
	 * @api-out binary $data The binary data is output directly, not JSON.
	 */
	public function actionGetData(ParameterBag $params)
	{
		$attachment = $this->assertViewableAttachment($params->attachment_id);

		/** @var AttachmentPlugin $attachPlugin */
		$attachPlugin = $this->plugin(AttachmentPlugin::class);

		return $attachPlugin->displayAttachment($attachment);
	}

	/**
	 * @api-desc Gets the URL to the attachment's thumbnail, if it has one. URL returned via a 301 redirect.
	 *
	 * @api-out string $url The URL to the thumbnail is returned via a 301 redirect's Location header.
	 *
	 * @api-error not_found Not found if the attachment does not have a thumbnail
	 */
	public function actionGetThumbnail(ParameterBag $params)
	{
		$attachment = $this->assertViewableAttachment($params->attachment_id);

		if (!$attachment->has_thumbnail)
		{
			return $this->notFound();
		}

		return $this->redirectPermanently($attachment->Data->getThumbnailUrl(true));
	}

	/**
	 * @api-desc Delete's the specified attachment.
	 *
	 * @api-out true $success
	 */
	public function actionDelete(ParameterBag $params)
	{
		$attachment = $this->assertViewableAttachment($params->attachment_id);

		$handler = $this->getAttachmentRepo()->getAttachmentHandler($attachment->content_type);
		if (!$handler)
		{
			return $this->noPermission();
		}

		// If the attachment has a temp hash, then it hasn't been associated yet.
		// If we have the hash/key that applies, then that should generally be sufficient.
		if (!$attachment->temp_hash)
		{
			$container = $attachment->Container;
			if (!$container)
			{
				return $this->noPermission();
			}

			$context = $handler->getContext($container);

			if (\XF::isApiCheckingPermissions() && !$handler->canManageAttachments($context, $error))
			{
				return $this->noPermission($error);
			}
		}

		$attachment->delete();

		return $this->apiSuccess();
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return Attachment
	 *
	 * @throws Exception
	 */
	protected function assertViewableAttachment($id, $with = 'api')
	{
		/** @var Attachment $attachment */
		$attachment = $this->assertRecordExists(Attachment::class, $id, $with);

		if ($attachment->temp_hash)
		{
			$hash = $this->filter('hash', 'str');
			if (!$hash)
			{
				$key = $this->filter('key', 'str');
				if ($key)
				{
					$key = $this->assertRecordExists(ApiAttachmentKey::class, $key);
					$hash = $key->temp_hash;
				}
			}

			if ($attachment->temp_hash !== $hash)
			{
				throw $this->exception($this->noPermission());
			}
		}
		else
		{
			if (\XF::isApiCheckingPermissions() && !$attachment->canView($error))
			{
				throw $this->exception($this->noPermission($error));
			}
		}

		return $attachment;
	}

	/**
	 * @return AttachmentRepository
	 */
	protected function getAttachmentRepo()
	{
		return $this->repository(AttachmentRepository::class);
	}
}
