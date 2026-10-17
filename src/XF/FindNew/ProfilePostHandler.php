<?php

namespace XF\FindNew;

use XF\Entity\FindNew;
use XF\Entity\ProfilePost;
use XF\Finder\ProfilePostFinder;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Repository\AttachmentRepository;
use XF\Repository\ProfilePostRepository;

/**
 * @extends AbstractHandler<ProfilePost>
 */
class ProfilePostHandler extends AbstractHandler
{
	public function getRoute()
	{
		return 'whats-new/profile-posts';
	}

	public function getPageReply(Controller $controller, FindNew $findNew, array $results, $page, $perPage)
	{
		$profilePostRepo = \XF::repository(ProfilePostRepository::class);
		$profilePosts = $profilePostRepo->addCommentsToProfilePosts($results);

		$attachmentRepo = \XF::repository(AttachmentRepository::class);

		$canInlineMod = false;
		$canViewAttachments = false;
		$profilePostAttachData = [];
		foreach ($profilePosts AS $profilePost)
		{
			if (!$canInlineMod && $profilePost->canUseInlineModeration())
			{
				$canInlineMod = true;
			}
			if (!$canViewAttachments && $profilePost->canViewAttachments())
			{
				$canViewAttachments = true;
			}
			if ($profilePost->canUploadAndManageAttachments())
			{
				$profilePostAttachData[$profilePost->profile_post_id] = $attachmentRepo->getEditorData('profile_post_comment', $profilePost);
			}
		}

		$viewParams = [
			'findNew' => $findNew,

			'page' => $page,
			'perPage' => $perPage,

			'profilePosts' => $profilePosts,
			'canInlineMod' => $canInlineMod,

			'canViewAttachments' => $canViewAttachments,
			'profilePostAttachData' => $profilePostAttachData,
		];
		return $controller->view('XF:WhatsNew\ProfilePosts', 'whats_new_profile_posts', $viewParams);
	}

	public function getFiltersFromInput(Request $request)
	{
		$filters = [];

		$visitor = \XF::visitor();
		$followed = $request->filter('followed', 'bool');

		if ($followed && $visitor->user_id)
		{
			$filters['followed'] = true;
		}

		return $filters;
	}

	public function getDefaultFilters()
	{
		return [];
	}

	public function getResultIds(array $filters, $maxResults)
	{
		$profilePostFinder = \XF::finder(ProfilePostFinder::class)
			->where('message_state', '<>', 'moderated')
			->where('message_state', '<>', 'deleted')
			->with(['ProfileUser', 'ProfileUser.Privacy'])
			->indexHint('USE', 'post_date')
			->order('post_date', 'DESC');

		if (!\XF::visitor()->canBypassUserPrivacy())
		{
			$profilePostFinder->where('ProfileUser.user_state', 'valid');
			$profilePostFinder->where('ProfileUser.is_banned', 0);
		}

		$this->applyFilters($profilePostFinder, $filters);

		$profilePosts = $profilePostFinder->fetch($maxResults);
		$profilePosts = $this->filterResults($profilePosts);

		// TODO: consider overfetching or some other permission limits within the query

		return $profilePosts->keys();
	}

	public function getPageResultsEntities(array $ids)
	{
		$ids = array_map('intval', $ids);

		$profilePostFinder = \XF::finder(ProfilePostFinder::class)
			->where('profile_post_id', $ids)
			->with('fullProfile');

		return $profilePostFinder->fetch();
	}

	protected function filterResults(AbstractCollection $results)
	{
		$visitor = \XF::visitor();

		return $results->filter(function (ProfilePost $profilePosts) use ($visitor)
		{
			return ($profilePosts->canView() && !$visitor->isIgnoring($profilePosts->user_id));
		});
	}

	protected function applyFilters(ProfilePostFinder $profilePostFinder, array $filters)
	{
		$visitor = \XF::visitor();

		if (!empty($filters['followed']))
		{
			$following = $visitor->Profile->following;
			$following[] = $visitor->user_id;

			$profilePostFinder->where('user_id', $following);
		}
	}

	public function getResultsPerPage()
	{
		return \XF::options()->messagesPerPage;
	}

	public function isAvailable()
	{
		return \XF::visitor()->canViewProfilePosts();
	}
}
