<?php

namespace XF\Pub\Controller;

use XF\Attachment\Manipulator;
use XF\ControllerPlugin\EditorPlugin;
use XF\Data\CodeLanguage;
use XF\Data\Emoji;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\AttachmentRepository;
use XF\Repository\BbCodeMediaSiteRepository;
use XF\Repository\EmojiRepository;
use XF\Repository\SmilieCategoryRepository;
use XF\Repository\SmilieRepository;
use XF\Util\Str;

class EditorController extends AbstractController
{
	public function actionDialog()
	{
		$dialog = preg_replace('/[^a-z0-9_]/i', '', $this->filter('dialog', 'str'));

		$data = $this->loadDialog($dialog);
		if (!$data['template'])
		{
			// prevents errors from being logged -- must explicitly define valid dialogs
			return $this->notFound();
		}

		return $this->view($data['view'], $data['template'], $data['params']);
	}

	protected function loadDialog($dialog)
	{
		$view = 'XF:Editor\Dialog';
		$template = null;
		$params = [];

		if ($dialog == 'code')
		{
			/** @var CodeLanguage $codeLanguageData */
			$codeLanguageData = $this->data(CodeLanguage::class);
			$params['languages'] = $codeLanguageData->getSupportedLanguages(true);
			$template = "editor_dialog_code";
		}
		else if ($dialog == 'media')
		{
			/** @var BbCodeMediaSiteRepository $mediaRepo */
			$mediaRepo = $this->repository(BbCodeMediaSiteRepository::class);
			$params['sites'] = $mediaRepo->findActiveMediaSites()->fetch();
			$template = "editor_dialog_media";
		}
		else if ($dialog == 'spoiler')
		{
			$template = "editor_dialog_spoiler";
		}

		$data = [
			'dialog' => $dialog,
			'view' => $view,
			'template' => $template,
			'params' => $params,
		];

		$this->app->fire('editor_dialog', [&$data, $this], $dialog);

		return $data;
	}

	public function actionMedia()
	{
		$this->assertPostOnly();

		/** @var BbCodeMediaSiteRepository $mediaRepo */
		$mediaRepo = $this->repository(BbCodeMediaSiteRepository::class);

		$url = $this->filter('url', 'str');
		$sites = $mediaRepo->findActiveMediaSites()->fetch();
		$match = $mediaRepo->urlMatchesMediaSiteList($url, $sites);

		$jsonParams = [];
		if ($match)
		{
			$jsonParams['matchBbCode'] = '[MEDIA=' . $match['media_site_id'] . ']' . $match['media_id'] . '[/MEDIA]';
		}
		else
		{
			$jsonParams['noMatch'] = \XF::phrase('specified_url_cannot_be_embedded_as_media');
		}

		$view = $this->view('XF:Editor\Media', '', []);
		$view->setJsonParams($jsonParams);
		return $view;
	}

	public function actionInsertGif()
	{
		$giphy = $this->app->giphyApi();
		if (!$giphy)
		{
			return $this->noPermission();
		}

		$offset = $this->filter('offset', 'uint', 0);
		$limit = 30;

		$trending = $giphy->getTrending($offset, $limit, $error);

		if ($error)
		{
			return $this->error(\XF::phrase('giphy_integration_is_not_currently_available_try_later'));
		}

		$viewParams = [
			'trending' => $trending,
			'nextOffset' => $offset + $limit,
		];
		return $this->view('XF:Editor\InsertGif', 'editor_insert_gif', $viewParams);
	}

	public function actionInsertGifSearch()
	{
		$giphy = $this->app->giphyApi();
		if (!$giphy)
		{
			return $this->noPermission();
		}

		$q = $this->filter('q', 'str');
		$offset = $this->filter('offset', 'uint', 0);
		$limit = 30;

		if ($q !== '' && Str::strlen($q) >= 2)
		{
			$results = $giphy->getSearchResults($q, $offset, $limit, $error);

			if ($error)
			{
				return $this->error(\XF::phrase('giphy_integration_is_not_currently_available_try_later'));
			}
		}
		else
		{
			$results = [];
			$q = '';
		}

		$viewParams = [
			'q' => $q,
			'results' => $results,
			'nextOffset' => $offset + $limit,
		];
		return $this->view('XF:Editor\InsertGif\Search', 'editor_insert_gif_search_results', $viewParams);
	}

	public function actionSmiliesEmoji()
	{
		/** @var SmilieRepository $smilieRepo */
		/** @var SmilieCategoryRepository $smilieCategoryRepo */
		$smilieRepo = $this->repository(SmilieRepository::class);
		$smilieCategoryRepo = $this->repository(SmilieCategoryRepository::class);

		$smilies = $smilieRepo->findSmiliesForList(true)->fetch();
		$smilieCategories = $smilieCategoryRepo->findSmilieCategoriesForList(true);
		$groupedSmilies = $smilies->groupBy('smilie_category_id');

		if ($this->options()->showEmojiInSmilieMenu)
		{
			/** @var Emoji $emojiData */
			$emojiData = $this->data(Emoji::class);
			$emojiList = $emojiData->getEmojiListForDisplay(true);
		}
		else
		{
			$emojiList = [];
		}

		$recent = [];
		$recentlyUsed = $this->request->getCookie('emoji_usage', '');
		if ($recentlyUsed)
		{
			$recentlyUsed = array_reverse(explode(',', $recentlyUsed));

			foreach ($recentlyUsed AS $shortname)
			{
				$matches = $smilieRepo->findSmiliesByTextFromSmilies($shortname, $smilies);
				if ($matches)
				{
					$recent[key($matches)] = reset($matches);
				}
				else if (isset($emojiList[$shortname]))
				{
					$recent[$shortname] = $emojiList[$shortname];
				}
			}
		}

		$groupedEmoji = [];
		$emojiCategories = [];

		foreach ($emojiList AS $unicode => $emoji)
		{
			$groupedEmoji[$emoji['category']][$unicode] = $emoji;

			if (!isset($emojiCategories[$emoji['category']]))
			{
				$emojiCategories[$emoji['category']] = $emoji['category_name'];
			}
		}

		$viewParams = [
			'recent' => $recent,
			'groupedSmilies' => $groupedSmilies,
			'smilieCategories' => $smilieCategories,
			'groupedEmoji' => $groupedEmoji,
			'emojiCategories' => $emojiCategories,
		];
		return $this->view('XF:Editor\SmiliesEmoji', 'editor_smilies_emoji', $viewParams);
	}

	public function actionSmiliesEmojiSearch()
	{
		$q = ltrim($this->filter('q', 'str', ['no-trim']));

		if ($q !== '' && Str::strlen($q) >= 2)
		{
			/** @var EmojiRepository $emojiRepo */
			$emojiRepo = $this->repository(EmojiRepository::class);
			$results = $emojiRepo->getMatchingEmojiByString($q, [
				'includeEmoji' => $this->options()->showEmojiInSmilieMenu,
				'limit' => 1000,
			]);
		}
		else
		{
			$results = [];
			$q = '';
		}

		$viewParams = [
			'q' => $q,
			'results' => $results,
		];
		return $this->view('XF:Editor\SmiliesEmoji\Search', 'editor_smilies_emoji_search_results', $viewParams);
	}

	public function actionToBbCode()
	{
		$this->assertPostOnly();

		$html = $this->filter('html', 'str,no-clean');
		$bbCode = $this->plugin(EditorPlugin::class)->convertToBbCode($html);

		$view = $this->view('XF:Editor\ToBbCode', '', []);
		$view->setJsonParams([
			'bbCode' => $bbCode,
		]);
		return $view;
	}

	public function actionToHtml()
	{
		$this->assertPostOnly();

		$bbCode = $this->filter('bb_code', 'str');

		$editorHtml = $this->app->bbCode()->render($bbCode, 'editorHtml', 'editor', null, [
			'attachments' => $this->getAvailableAttachments(),
		]);

		$view = $this->view('XF:Editor\ToHtml', '', []);
		$view->setJsonParams([
			'editorHtml' => $editorHtml,
		]);
		return $view;
	}

	protected function getAvailableAttachments()
	{
		$rawAttachmentData = $this->filter('attachment_hash_combined', 'json-array');
		$attachmentData = $this->filterArray($rawAttachmentData, [
			'type' => 'str',
			'context' => 'array-str',
			'hash' => 'str',
		]);

		$attachRepo = $this->repository(AttachmentRepository::class);

		$handler = $attachRepo->getAttachmentHandler($attachmentData['type']);
		if (!$handler)
		{
			return [];
		}

		if (!$handler->canManageAttachments($attachmentData['context'], $error))
		{
			return [];
		}

		$class = \XF::extendClass(Manipulator::class);
		$manipulator = new $class(
			$handler,
			$attachRepo,
			$attachmentData['context'],
			$attachmentData['hash']
		);
		$existing = $manipulator->getExistingAttachments();
		$new = $manipulator->getNewAttachments();

		return $existing + $new;
	}

	public function updateSessionActivity($action, ParameterBag $params, AbstractReply &$reply)
	{
	}
}
