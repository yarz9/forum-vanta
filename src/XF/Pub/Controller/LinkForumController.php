<?php

namespace XF\Pub\Controller;

use XF\Entity\LinkForum;
use XF\Finder\LinkForumFinder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Repository\NodeRepository;

use function is_int;

class LinkForumController extends AbstractController
{
	public function actionIndex(ParameterBag $params)
	{
		if (!$params->node_id && !$params->node_name)
		{
			return $this->redirectPermanently($this->buildLink('forums'));
		}

		$linkForum = $this->assertViewableLinkForum($params->node_id ?: $params->node_name);

		return $this->redirectPermanently($linkForum->link_url);
	}

	/**
	 * @param int $nodeId
	 * @param array $extraWith
	 *
	 * @return LinkForum
	 *
	 * @throws Exception
	 */
	protected function assertViewableLinkForum($nodeIdOrName, array $extraWith = [])
	{
		if ($nodeIdOrName === null)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_forum_not_found')));
		}

		$visitor = \XF::visitor();
		$extraWith[] = 'Node.Permissions|' . $visitor->permission_combination_id;

		$finder = $this->em()->getFinder(LinkForumFinder::class);
		$finder->with('Node', true)->with($extraWith);
		if (is_int($nodeIdOrName) || $nodeIdOrName === (string) (int) $nodeIdOrName)
		{
			$finder->where('node_id', $nodeIdOrName);
		}
		else
		{
			$finder->where(['Node.node_name' => $nodeIdOrName, 'Node.node_type_id' => 'LinkForum']);
		}

		/** @var LinkForum $forum */
		$forum = $finder->fetchOne();
		if (!$forum)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_forum_not_found')));
		}
		if (!$forum->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $forum;
	}

	/**
	 * @return NodeRepository
	 */
	protected function getNodeRepo()
	{
		return $this->repository(NodeRepository::class);
	}
}
