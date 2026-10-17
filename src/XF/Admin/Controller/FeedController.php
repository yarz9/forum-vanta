<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\Feed;
use XF\Entity\Forum;
use XF\Entity\Node;
use XF\Finder\FeedFinder;
use XF\Finder\UserFinder;
use XF\Mvc\ParameterBag;
use XF\Repository\FeedRepository;
use XF\Repository\NodeRepository;
use XF\Service\Feed\FeederService;
use XF\Service\Feed\ReaderService;

use function count, intval;

class FeedController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('thread');
	}

	public function actionIndex(ParameterBag $params)
	{
		$feedRepo = $this->getFeedRepo();

		$viewParams = [
			'feeds' => $feedRepo->findFeedsForList()->fetch(),
		];
		return $this->view('XF:Feed\Listing', 'feed_list', $viewParams);
	}

	public function feedAddEdit(Feed $feed)
	{
		$nodeRepo = $this->repository(NodeRepository::class);

		$prefixes = [];
		if ($feed->node_id)
		{
			/** @var Node $node */
			$node = $this->em()->find(Node::class, $feed->node_id);
			if ($node)
			{
				/** @var Forum $forum */
				$forum = $node->getDataRelationOrDefault();
				$prefixes = $forum->getPrefixesGrouped();
			}
		}

		$viewParams = [
			'feed' => $feed,
			'forums' => $nodeRepo->getNodeOptionsData(true, 'Forum'),
			'prefixes' => $prefixes,
		];
		return $this->view('XF:Feed\Edit', 'feed_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$feed = $this->assertFeedExists($params->feed_id);
		return $this->feedAddEdit($feed);
	}

	public function actionAdd()
	{
		$feed = $this->em()->create(Feed::class);

		return $this->feedAddEdit($feed);
	}

	protected function getFeedInput()
	{
		return $this->filter([
			'title' => 'str',
			'url' => 'str',
			'frequency' => 'uint',
			'active' => 'bool',

			'user_id' => 'int',

			'node_id' => 'uint',
			'prefix_id' => 'uint',
			'title_template' => 'str',
			'message_template' => 'str',

			'discussion_visible' => 'bool',
			'discussion_open' => 'bool',
			'discussion_sticky' => 'bool',
		]);
	}

	protected function feedSaveProcess(Feed $feed)
	{
		$form = $this->formAction();

		$input = $this->getFeedInput();
		if ($input['user_id'] == -1)
		{
			$username = $this->filter('username', 'str');
			$user = $this->finder(UserFinder::class)->where('username', $username)->fetchOne();
			if ($user)
			{
				$input['user_id'] = $user['user_id'];
			}
			else
			{
				throw $this->exception($this->error(\XF::phrase('please_enter_valid_name')));
			}
		}
		$input['user_id'] = intval(max($input['user_id'], 0));

		$reader = $this->getFeedReader($input['url']);
		$feedData = $reader->getFeedData(false);

		$this->assertValidFeedData($feedData, $reader, false);

		if (!$input['title'] && !empty($feedData['title']))
		{
			$input['title'] = $feedData['title'];
		}

		$form->basicEntitySave($feed, $input);

		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($this->request->exists('preview'))
		{
			return $this->rerouteController(self::class, 'preview', $params);
		}

		if ($params->feed_id)
		{
			$feed = $this->assertFeedExists($params->feed_id);
		}
		else
		{
			$feed = $this->em()->create(Feed::class);
		}

		$this->feedSaveProcess($feed)->run();

		return $this->redirect($this->buildLink('feeds'));
	}

	public function actionPreview()
	{
		$input = $this->getFeedInput();

		/** @var Feed $feed */
		$feed = $this->em()->create(Feed::class);
		$feed->bulkSet($input);

		$reader = $this->getFeedReader($feed['url']);
		$feedData = $reader->getFeedData();

		$this->assertValidFeedData($feedData, $reader);

		if (!$feed->title && $feedData['title'])
		{
			$feed->title = $feedData['title'];
		}

		$entry = $feedData['entries'][mt_rand(0, count($feedData['entries']) - 1)];

		$title = $feed->getEntryTitle($entry);
		$message = $feed->getEntryMessage($entry);

		$entry['title'] = $title;
		$entry['message'] = $message;

		if ($input['user_id'] == 0)
		{
			$entry['author'] = $feed->title;
		}
		else if ($input['user_id'] == -1)
		{
			$entry['author'] = $this->filter('username', 'str');
		}

		$viewParams = [
			'feed' => $feed,
			'feedData' => $feedData,
			'entry' => $entry,
		];
		return $this->view('XF:\Feed\Preview', 'feed_preview', $viewParams);
	}

	public function actionDelete(ParameterBag $params)
	{
		$feed = $this->assertFeedExists($params->feed_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$feed,
			$this->buildLink('feeds/delete', $feed),
			$this->buildLink('feeds/edit', $feed),
			$this->buildLink('feeds'),
			$feed->title
		);
	}

	public function actionToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(FeedFinder::class);
	}

	public function actionImport(ParameterBag $params)
	{
		$feed = $this->assertFeedExists($params->feed_id, ['Forum', 'Forum.Node']);
		if (!$feed->Forum)
		{
			throw $this->exception($this->error(\XF::phrase('cannot_find_associated_forum_node')));
		}

		$feeder = $this->getFeedFeeder($feed->url);
		if ($feeder->setupImport($feed, true) && $feeder->countPendingEntries())
		{
			$feeder->importEntries();
		}
		return $this->redirect($this->buildLink('feeds'));
	}

	protected function assertValidFeedData($feedData, ReaderService $reader, $checkEntries = true)
	{
		if (!$feedData || ($checkEntries && empty($feedData['entries'])))
		{
			throw $this->exception($this->error(
				\XF::phrase('there_was_problem_requesting_feed', [
					'message' => $reader->getException()
						? $reader->getException()->getMessage()
						: \XF::phrase('n_a'),
				])
			));
		}
	}

	/**
	 * @param $url
	 *
	 * @return ReaderService
	 */
	protected function getFeedReader($url)
	{
		return $this->service(ReaderService::class, $url);
	}

	/**
	 * @param $url
	 *
	 * @return FeederService
	 */
	protected function getFeedFeeder($url)
	{
		return $this->service(FeederService::class, $url);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return Feed
	 */
	protected function assertFeedExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(Feed::class, $id, $with, $phraseKey);
	}

	/**
	 * @return FeedRepository
	 */
	protected function getFeedRepo()
	{
		return $this->repository(FeedRepository::class);
	}
}
