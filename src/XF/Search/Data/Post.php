<?php

namespace XF\Search\Data;

use XF\Entity\Forum;
use XF\Finder\ForumFinder;
use XF\Http\Request;
use XF\Mvc\Entity\Entity;
use XF\Repository\NodeRepository;
use XF\Repository\ThreadPrefixRepository;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Search\Query\KeywordQuery;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\Query;
use XF\Search\Query\SqlConstraint;
use XF\Search\Query\SqlOrder;
use XF\Search\Query\TableReference;
use XF\Tree;

/**
 * @extends AbstractData<\XF\Entity\Post>
 */
class Post extends AbstractData
{
	public function getEntityWith($forView = false)
	{
		$get = ['Thread', 'Thread.Forum'];
		if ($forView)
		{
			$get[] = 'User';

			$visitor = \XF::visitor();
			$get[] = 'Thread.Forum.Node.Permissions|' . $visitor->permission_combination_id;
		}

		return $get;
	}

	public function getIndexData(Entity $entity)
	{
		if (!$entity->Thread || !$entity->Thread->Forum)
		{
			return null;
		}

		$thread = $entity->Thread;

		if ($entity->isFirstPost())
		{
			return $this->searcher->handler('thread')->getIndexData($thread);
		}

		$index = IndexRecord::create('post', $entity->post_id, [
			'message' => $entity->message_,
			'date' => $entity->post_date,
			'user_id' => $entity->user_id,
			'discussion_id' => $entity->thread_id,
			'metadata' => $this->getMetaData($entity),
		]);

		if (!$entity->isVisible())
		{
			$index->setHidden();
		}

		return $index;
	}

	protected function getMetaData(\XF\Entity\Post $entity)
	{
		$thread = $entity->Thread;

		$metadata = [
			'node' => $thread->node_id,
			'thread' => $entity->thread_id,
		];
		if ($thread->prefix_id)
		{
			$metadata['prefix'] = $thread->prefix_id;
		}

		return $metadata;
	}

	public function setupMetadataStructure(MetadataStructure $structure)
	{
		$structure->addField('node', MetadataStructure::INT);
		$structure->addField('thread', MetadataStructure::INT);
		$structure->addField('prefix', MetadataStructure::INT);
	}

	public function canIncludeInResults(Entity $entity, array $resultIds)
	{
		if (isset($resultIds['thread-' . $entity->thread_id]) && $entity->isFirstPost())
		{
			return false;
		}

		return true;
	}

	public function getResultDate(Entity $entity)
	{
		return $entity->post_date;
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'post' => $entity,
			'options' => $options,
		];
	}

	public function getSearchableContentTypes()
	{
		return ['post', 'thread'];
	}

	public function getSearchFormTab()
	{
		return [
			'title' => \XF::phrase('search_threads'),
			'order' => 10,
		];
	}

	public function getSectionContext()
	{
		return 'forums';
	}

	public function getSearchFormData()
	{
		$prefixListData = $this->getPrefixListData();

		return [
			'prefixGroups' => $prefixListData['prefixGroups'],
			'prefixesGrouped' => $prefixListData['prefixesGrouped'],

			'nodeTree' => $this->getSearchableNodeTree(),
		];
	}

	/**
	 * @return Tree
	 */
	protected function getSearchableNodeTree()
	{
		/** @var NodeRepository $nodeRepo */
		$nodeRepo = \XF::repository(NodeRepository::class);
		$nodeTree = $nodeRepo->createNodeTree($nodeRepo->getNodeList());

		// only list nodes that are forums or contain forums
		$nodeTree = $nodeTree->filter(null, function ($id, $node, $depth, $children, $tree)
		{
			return ($children || $node->node_type_id == 'Forum');
		});

		return $nodeTree;
	}

	protected function getPrefixListData()
	{
		/** @var ThreadPrefixRepository $prefixRepo */
		$prefixRepo = \XF::repository(ThreadPrefixRepository::class);
		return $prefixRepo->getVisiblePrefixListData();
	}

	public function applyTypeConstraintsFromInput(Query $query, Request $request, array &$urlConstraints)
	{
		$minReplyCount = $request->filter('c.min_reply_count', 'uint');
		if ($minReplyCount)
		{
			$query->withSql(new SqlConstraint(
				'thread.reply_count >= %s',
				$minReplyCount,
				$this->getThreadQueryTableReference()
			));
		}
		else
		{
			unset($urlConstraints['min_reply_count']);
		}

		$prefixes = $request->filter('c.prefixes', 'array-uint');
		$prefixes = array_values(array_unique($prefixes));
		if ($prefixes && reset($prefixes))
		{
			$query->withMetadata('prefix', $prefixes);
		}
		else
		{
			unset($urlConstraints['prefixes']);
		}

		$threadId = $request->filter('c.thread', 'uint');
		if ($threadId)
		{
			$query->withMetadata('thread', $threadId);

			if ($query instanceof KeywordQuery)
			{
				$query->inTitleOnly(false);
			}
		}
		else
		{
			unset($urlConstraints['thread']);

			$nodeIds = $request->filter('c.nodes', 'array-uint');
			$nodeIds = array_values(array_unique($nodeIds));
			if ($nodeIds && reset($nodeIds))
			{
				if ($request->filter('c.child_nodes', 'bool'))
				{
					/** @var NodeRepository $nodeRepo */
					$nodeRepo = \XF::repository(NodeRepository::class);
					$nodeTree = $nodeRepo->createNodeTree($nodeRepo->getFullNodeListWithTypeData()->filterViewable());

					$searchNodeIds = array_fill_keys($nodeIds, true);
					$nodeTree->traverse(function ($id, $node) use (&$searchNodeIds)
					{
						if (isset($searchNodeIds[$id]) || isset($searchNodeIds[$node->parent_node_id]))
						{
							// if we're in the search node list, the user selected the node explicitly
							// if the parent is in the list, then that node was selected via traversal so we're included too
							$searchNodeIds[$id] = true;
						}

						// we still need to traverse children though, as children may be selected
					});

					$nodeIds = array_unique(array_keys($searchNodeIds));
				}
				else
				{
					unset($urlConstraints['child_nodes']);
				}

				$query->withMetadata('node', $nodeIds);
			}
			else
			{
				unset($urlConstraints['nodes']);
				unset($urlConstraints['child_nodes']);
			}
		}

		// this will implicitly limit results to just the thread record so it's not currently exposed to the UI
		$threadType = $request->filter('c.thread_type', 'str');
		if ($threadType)
		{
			$query->withMetadata('thread_type', $threadType);
		}
		else
		{
			unset($urlConstraints['thread_type']);
		}
	}

	public function getTypePermissionConstraints(Query $query, $isOnlyType)
	{
		$skip = [];
		$forums = \XF::em()->getFinder(ForumFinder::class)
			->with('Node.Permissions|' . \XF::visitor()->permission_combination_id)
			->fetch();

		/** @var Forum $forum */
		foreach ($forums AS $forum)
		{
			if (!$forum->canView() || !$forum->canViewThreadContent())
			{
				$skip[] = $forum->node_id;
			}
		}

		if ($skip)
		{
			return [
				new MetadataConstraint('node', $skip, MetadataConstraint::MATCH_NONE),
			];
		}
		else
		{
			return [];
		}
	}

	public function getTypeOrder($order)
	{
		if ($order == 'replies')
		{
			return new SqlOrder('thread.reply_count DESC', $this->getThreadQueryTableReference());
		}
		else
		{
			return null;
		}
	}

	protected function getThreadQueryTableReference()
	{
		return new TableReference(
			'thread',
			'xf_thread',
			'thread.thread_id = search_index.discussion_id'
		);
	}

	public function getGroupByType()
	{
		return 'thread';
	}

	public function canUseInlineModeration(Entity $entity, &$error = null)
	{
		return $entity->canUseInlineModeration($error);
	}
}
