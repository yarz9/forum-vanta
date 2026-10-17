<?php

namespace XF\ControllerPlugin;

use XF\Entity\Reaction;
use XF\Entity\ReactionContent;
use XF\Entity\ReactionTrait;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\Exception;
use XF\Mvc\Reply\View;
use XF\Repository\ReactionRepository;

use function count;

class ReactionPlugin extends AbstractPlugin
{
	public function actionReactSimple(Entity $content, $contentRoute)
	{
		return $this->actionReact($content, $contentRoute, "$contentRoute/react", "$contentRoute/reactions");
	}

	public function actionReact(Entity $content, $contentRoute, $actionRoute, $listRoute)
	{
		if ($this->isPost())
		{
			return $this->actionToggleReaction($content, $listRoute, $contentRoute);
		}
		else
		{
			return $this->actionConfirmReaction($content, $actionRoute, $contentRoute);
		}
	}

	/**
	 * @param Entity|ReactionTrait $content
	 * @param string|array $actionRoute
	 * @param string|array $contentRoute
	 *
	 * @throws Exception
	 */
	public function actionConfirmReaction(Entity $content, $actionRoute, $contentRoute)
	{
		$reaction = $this->validateReactionAction($content, $existingReaction);

		$isUnreact = ($existingReaction && $existingReaction->reaction_id == $reaction->reaction_id);

		$viewParams = [
			'confirmUrl' => $this->buildLink($actionRoute, $content),
			'reaction' => $reaction,
			'isUnreact' => $isUnreact,
		];
		return $this->view('XF:Reaction\Confirm', 'reaction_confirm', $viewParams);
	}

	/**
	 * @param Entity|ReactionTrait $content
	 * @param string|array $listRoute Route to display the reactions for this content; if array, use: [route, params as array]
	 * @param string|array $contentRoute Route to return to the content
	 *
	 * @return View
	 * @throws Exception
	 */
	public function actionToggleReaction(Entity $content, $listRoute, $contentRoute)
	{
		$requestedReaction = $this->validateReactionAction($content);

		$reaction = $this->getReactionRepo()->reactToContent(
			$requestedReaction->reaction_id,
			$content->getEntityContentType(),
			$content->getEntityId(),
			\XF::visitor(),
			true
		);

		if ($this->filter('_xfWithData', 'bool'))
		{
			$viewParams = [
				'reaction' => $reaction ?: $this->app->container('reactionDefault'),
				'content' => $content,
				'link' => $listRoute,
			];
			$reply = $this->view('XF:Reaction\React', '', $viewParams);

			if ($reaction)
			{
				$reply->setJsonParams([
					'reactionId' => $reaction['reaction_id'],
					'linkReactionId' => $reaction['reaction_id'],
				]);
			}
			else
			{
				$reactionDefault = $this->app->container('reactionDefault');

				$reply->setJsonParams([
					'reactionId' => null,
					'linkReactionId' => $reactionDefault['reaction_id'],
				]);
			}

			return $reply;
		}
		else
		{
			throw $this->exception(
				$this->redirect(
					$this->buildLink($contentRoute, $content)
				)
			);
		}
	}

	/**
	 * @param Entity|ReactionTrait $content
	 * @param null|ReactionContent Existing reaction if there is one
	 *
	 * @return Reaction
	 */
	protected function validateReactionAction(Entity $content, &$existingReaction = null)
	{
		$existingReaction = null;

		if (!$content->canReact($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		$reactionId = $this->filter('reaction_id', 'uint');
		if (!$reactionId)
		{
			throw $this->exception($this->notFound());
		}

		$visitor = \XF::visitor();

		$contentType = $content->getEntityContentType();
		$contentId = $content->getEntityId();

		if (!$contentType)
		{
			throw new \InvalidArgumentException("Provided entity must define a content type in its structure");
		}

		/** @var Reaction $reaction */
		$reaction = $this->em()->find(Reaction::class, $reactionId);
		if (!$reaction)
		{
			throw $this->exception($this->notFound());
		}

		$reactRepo = $this->getReactionRepo();
		$existingReaction = $reactRepo->getReactionByContentAndReactionUser(
			$contentType,
			$contentId,
			$visitor->user_id
		);

		if (!$reaction->active)
		{
			if (!$existingReaction || $existingReaction->reaction_id != $reaction->reaction_id)
			{
				throw $this->exception($this->notFound());
			}
		}

		return $reaction;
	}

	public function actionReactions(Entity $content, $link, $title = null, array $breadcrumbs = [], array $linkParams = [])
	{
		$contentType = $content->getEntityContentType();
		$contentId = $content->getEntityId();

		if (!$contentType)
		{
			throw new \InvalidArgumentException("Provided entity must defined a content type in its structure");
		}

		$reactionRepo = $this->getReactionRepo();

		$page = $this->filterPage();
		$perPage = 50;

		$reactionId = $this->filter('reaction_id', 'uint');

		$reactionsFinder = $reactionRepo->findContentReactions($contentType, $contentId, $reactionId)
			->limitByPage($page, $perPage, 1);

		/** @var ArrayCollection|ReactionContent[] $reactions */
		$reactions = $reactionsFinder->fetch();

		if (!count($reactions))
		{
			return $this->message(\XF::phrase('no_one_has_reacted_to_this_content_yet'));
		}

		$hasNext = count($reactions) > $perPage;
		$reactions = $reactions->slice(0, $perPage);

		$reactionRepo->addContentToReactions($reactions);
		$reactions = $reactions->filter(function (ReactionContent $reaction)
		{
			return $reaction->canView() && $reaction->isRenderable();
		});

		$tabSummary = $reactionRepo->getContentTabSummary($contentType, $contentId);

		$viewParams = [
			'type' => $contentType,
			'id' => $contentId,

			'content' => $content,
			'link' => $link,
			'linkParams' => $linkParams,

			'tabSummary' => $tabSummary,

			'activeReactionId' => $reactionId,
			'reactions' => $reactions,
			'hasNext' => $hasNext,
			'page' => $page,

			'title' => $title,
			'breadcrumbs' => $breadcrumbs,

			'listOnly' => $this->filter('list_only', 'bool'),
		];
		return $this->view('XF:Reaction\Listing', 'reaction_list', $viewParams);
	}

	/**
	 * @return ReactionRepository
	 */
	protected function getReactionRepo()
	{
		return $this->repository(ReactionRepository::class);
	}
}
