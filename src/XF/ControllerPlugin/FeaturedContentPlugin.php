<?php

namespace XF\ControllerPlugin;

use XF\Entity\FeaturedContent;
use XF\Entity\FeatureTrait;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\FeaturedContentRepository;
use XF\Repository\UserRepository;
use XF\Service\FeaturedContent\CreatorService;
use XF\Service\FeaturedContent\DeleterService;
use XF\Service\FeaturedContent\EditorService;

class FeaturedContentPlugin extends AbstractPlugin
{
	public function actionFeature(
		Entity $content,
		array $breadcrumbs,
		string $confirmUrl,
		string $deleteUrl
	): AbstractReply
	{
		/** @var Entity|FeatureTrait $content */
		if (!$content->canFeatureUnfeature($error))
		{
			return $this->noPermission($error);
		}

		if ($content->isFeatured() && $content->Feature)
		{
			return $this->actionEdit(
				$content,
				$breadcrumbs,
				$confirmUrl,
				$deleteUrl
			);
		}

		return $this->actionCreate($content, $breadcrumbs, $confirmUrl);
	}

	public function actionCreate(
		Entity $content,
		array $breadcrumbs,
		string $confirmUrl
	): AbstractReply
	{
		if ($this->isPost())
		{
			$creator = $this->setupFeatureCreation($content);
			if (!$creator->validate($errors))
			{
				return $this->error($errors);
			}

			$creator->save();
			$this->finalizeFeatureCreation($creator);

			return $this->redirect($this->getDynamicRedirect());
		}

		$showVisibilityToggle = $this->allowVisibilityToggle($content);

		$viewParams = [
			'content' => $content,
			'breadcrumbs' => $breadcrumbs,
			'confirmUrl' => $confirmUrl,
			'showVisibilityToggle' => $showVisibilityToggle,
		];
		return $this->view(
			'XF:FeaturedContent\Create',
			'featured_content_create',
			$viewParams
		);
	}

	protected function setupFeatureCreation(Entity $content): CreatorService
	{
		/** @var CreatorService $creator */
		$creator = $this->service(CreatorService::class, $content);

		$title = $this->filter('title', 'str');
		$snippet = $this->filter('snippet', 'str');
		$image = $this->request->getFile('image', false, false);

		$creator->setTitle($title);
		$creator->setSnippet($snippet);
		if ($image)
		{
			$creator->setImageFromUpload($image);
		}

		if ($this->allowVisibilityToggle($content))
		{
			$alwaysVisible = $this->filter('always_visible', 'bool');
			$creator->setAlwaysVisible($alwaysVisible);
		}

		return $creator;
	}

	protected function finalizeFeatureCreation(CreatorService $creator)
	{
	}

	public function actionEdit(
		Entity $content,
		array $breadcrumbs,
		string $confirmUrl,
		string $deleteUrl
	): AbstractReply
	{
		/** @var Entity|FeatureTrait $content */
		$feature = $content->Feature;

		if ($this->isPost())
		{
			$editor = $this->setupFeatureEdit($feature);
			if (!$editor->validate($errors))
			{
				return $this->error($errors);
			}

			$editor->save();
			$this->finalizeFeatureEdit($editor);

			return $this->redirect($this->getDynamicRedirect());
		}

		$showVisibilityToggle = $this->allowVisibilityToggle($content);

		$viewParams = [
			'content' => $content,
			'breadcrumbs' => $breadcrumbs,
			'confirmUrl' => $confirmUrl,
			'deleteUrl' => $deleteUrl,
			'feature' => $feature,
			'showVisibilityToggle' => $showVisibilityToggle,
		];
		return $this->view(
			'XF:FeaturedContent\Edit',
			'featured_content_edit',
			$viewParams
		);
	}

	protected function setupFeatureEdit(FeaturedContent $feature): EditorService
	{
		/** @var EditorPlugin $editor */
		$editor = $this->service(EditorService::class, $feature);

		$title = $this->filter('title', 'str');
		$snippet = $this->filter('snippet', 'str');
		$image = $this->request->getFile('image', false, false);
		$deleteImage = $this->filter('delete_image', 'bool');
		$date = $this->filter('date', 'datetime');

		$editor->setAutoFeatured(false);
		$editor->setTitle($title);
		$editor->setSnippet($snippet);
		$editor->setDate($date);

		if ($image)
		{
			$editor->setImageFromUpload($image);
		}
		else if ($feature->image_date && $deleteImage)
		{
			$editor->setDeleteImage(true);
		}

		if ($this->allowVisibilityToggle($feature->Content, $feature))
		{
			$alwaysVisible = $this->filter('always_visible', 'bool');
			$editor->setAlwaysVisible($alwaysVisible);
		}

		return $editor;
	}

	protected function finalizeFeatureEdit(EditorService $editor)
	{
	}

	public function actionUnfeature(
		Entity $content,
		array $breadcrumbs,
		string $confirmUrl
	): AbstractReply
	{
		/** @var Entity|FeatureTrait $content */
		if (!$content->canFeatureUnfeature($error))
		{
			return $this->noPermission($error);
		}

		if (!$content->isFeatured() || !$content->Feature)
		{
			return $this->notFound();
		}

		$feature = $content->Feature;

		if ($this->isPost())
		{
			/** @var DeleterService $deleter */
			$deleter = $this->service(DeleterService::class, $feature);
			$deleter->delete();

			return $this->redirect($this->getDynamicRedirect());
		}

		$viewParams = [
			'content' => $content,
			'breadcrumbs' => $breadcrumbs,
			'confirmUrl' => $confirmUrl,
			'feature' => $feature,
		];
		return $this->view(
			'XF:FeaturedContent\Delete',
			'featured_content_delete',
			$viewParams
		);
	}

	protected function allowVisibilityToggle(
		Entity $content,
		?FeaturedContent $feature = null
	): bool
	{
		if ($feature && $feature->always_visible)
		{
			return true;
		}

		$handler = $this->getFeatureRepo()->getFeatureHandler(
			$content->getEntityContentType()
		);
		/** @var UserRepository $userRepo */
		$userRepo = $this->repository(UserRepository::class);

		return \XF::asVisitor(
			$userRepo->getGuestUser(),
			function () use ($handler, $content)
			{
				return !$handler->canViewContent($content);
			}
		);
	}

	protected function getFeatureRepo(): FeaturedContentRepository
	{
		return $this->repository(FeaturedContentRepository::class);
	}
}
