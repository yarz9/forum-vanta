<?php

namespace XF\Admin\Controller;

use XF\ControllerPlugin\AdminSectionPlugin;
use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\AdminLog;
use XF\Entity\CookieConsentLog;
use XF\Entity\EmailBounceLog;
use XF\Entity\ErrorLog;
use XF\Entity\ImageProxy;
use XF\Entity\LinkProxy;
use XF\Entity\ModeratorLog;
use XF\Entity\Oembed;
use XF\Entity\PaymentProviderLog;
use XF\Entity\SpamCleanerLog;
use XF\Entity\SpamTriggerLog;
use XF\Entity\User;
use XF\Entity\UsernameChange;
use XF\Entity\UserReject;
use XF\Finder\ErrorLogFinder;
use XF\Finder\PaymentProfileFinder;
use XF\Finder\PaymentProviderLogFinder;
use XF\Finder\PurchasableFinder;
use XF\Finder\UserFinder;
use XF\Finder\UsernameChangeFinder;
use XF\LogSearch\Searcher;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Repository\AdminLogRepository;
use XF\Repository\ChangeLogRepository;
use XF\Repository\CookieConsentRepository;
use XF\Repository\EmailBounceRepository;
use XF\Repository\ErrorLogRepository;
use XF\Repository\ImageProxyRepository;
use XF\Repository\LinkProxyRepository;
use XF\Repository\ModeratorLogRepository;
use XF\Repository\OembedRepository;
use XF\Repository\SitemapLogRepository;
use XF\Repository\SpamRepository;
use XF\Repository\UsernameChangeRepository;
use XF\Repository\UserRejectRepository;
use XF\Service\ImageProxyService;
use XF\Service\OembedService;
use XF\Util\Ip;
use XF\Util\Str;

use function count, strlen;

class LogController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('viewLogs');
	}

	public function actionIndex()
	{
		return $this->plugin(AdminSectionPlugin::class)->actionView('logs');
	}

	public function actionSearch()
	{
		/** @var Searcher $searcher */
		$searcher = $this->app['logSearcher'];

		$input = $this->filter([
			'q' => 'string',
			'typeChoices' => 'array',
			'start' => 'datetime',
			'end' => 'datetime',
		]);

		if ($input['q'])
		{
			$resultTypeSets = $searcher->search($input['q'], $input['typeChoices'], $input['start'], $input['end']);
		}
		else
		{
			$resultTypeSets = null;
		}

		$typeOptions = $searcher->getSearcherNamesForList();
		if (empty($input['typeChoices']))
		{
			$input['typeChoices'] = array_keys($typeOptions);
		}

		$viewParams = array_merge($input, [
			'typeOptions' => $typeOptions,
			'resultTypeSets' => $resultTypeSets,
		]);
		$viewParams['start'] = $viewParams['start'] ?: '';
		$viewParams['end'] = $viewParams['end'] ?: '';

		return $this->view('XF:Log\SearchForm', 'log_search', $viewParams);
	}

	public function actionServerError(ParameterBag $params)
	{
		if ($params->error_id)
		{
			$entry = $this->assertErrorLogExists($params->error_id, null, 'requested_log_entry_not_found');

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view('XF:Log\ServerError\View', 'log_server_error_view', $viewParams);
		}
		else
		{
			$page = $this->filterPage();
			$perPage = 20;

			$entries = $this->finder(ErrorLogFinder::class)
				->order('error_id', 'DESC')
				->limitByPage($page, $perPage);

			$viewParams = [
				'entries' => $entries->fetch(),

				'page' => $page,
				'perPage' => $perPage,
				'total' => $entries->total(),
			];
			return $this->view('XF:Log\ServerError\Listing', 'log_server_error_list', $viewParams);
		}
	}

	public function actionServerErrorDelete(ParameterBag $params)
	{
		$entry = $this->assertErrorLogExists($params->error_id);

		$entryArr = $entry->toArray();
		$entryArr['shortMessage'] = Str::substr($entry->message, 0, 75);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$entry,
			$this->buildLink('logs/server-errors/delete', $entry),
			null,
			$this->buildLink('logs/server-errors'),
			"{$entryArr['shortMessage']} - {$entry->filename}:{$entry->line}"
		);
	}

	public function actionServerErrorClear()
	{
		if ($this->isPost())
		{
			$this->getErrorLogRepo()->clearErrorLog();

			return $this->redirect($this->buildLink('logs/server-errors'));
		}
		else
		{
			return $this->view('XF:Log\ServerError\Clear', 'log_server_error_clear');
		}
	}

	public function actionModerator(ParameterBag $params)
	{
		if ($params->moderator_log_id)
		{
			$entry = $this->assertModeratorLogExists($params->moderator_log_id, null, 'requested_log_entry_not_found');

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view('XF:Log\Moderator\View', 'log_moderator_view', $viewParams);
		}
		else
		{
			$page = $this->filterPage();
			$perPage = 20;

			/** @var ModeratorLogRepository $modLogRepo */
			$modLogRepo = $this->repository(ModeratorLogRepository::class);

			$logFinder = $modLogRepo->findLogsForList()
				->limitByPage($page, $perPage);

			$linkFilters = [];
			if ($userId = $this->filter('user_id', 'uint'))
			{
				$linkFilters['user_id'] = $userId;
				$logFinder->where('user_id', $userId);
			}

			// TODO: support for other filters/sorting?

			if ($this->isPost())
			{
				// redirect to give a linkable page
				return $this->redirect($this->buildLink('logs/moderator', null, $linkFilters));
			}

			$viewParams = [
				'entries' => $logFinder->fetch(),
				'logUsers' => $modLogRepo->getUsersInLog(),

				'userId' => $userId,

				'page' => $page,
				'perPage' => $perPage,
				'total' => $logFinder->total(),
				'linkFilters' => $linkFilters,
			];
			return $this->view('XF:Log\Moderator\Listing', 'log_moderator_list', $viewParams);
		}
	}

	public function actionAdmin(ParameterBag $params)
	{
		$this->assertSuperAdmin();

		if ($params->admin_log_id)
		{
			$entry = $this->assertAdminLogExists($params->admin_log_id, null, 'requested_log_entry_not_found');

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view('XF:Log\Admin\View', 'log_admin_view', $viewParams);
		}
		else
		{
			$page = $this->filterPage();
			$perPage = 20;

			/** @var AdminLogRepository $adminLogRepo */
			$adminLogRepo = $this->repository(AdminLogRepository::class);

			$logFinder = $adminLogRepo->findLogsForList()
				->limitByPage($page, $perPage);

			$linkFilters = [];
			if ($userId = $this->filter('user_id', 'uint'))
			{
				$linkFilters['user_id'] = $userId;
				$logFinder->where('user_id', $userId);
			}

			// TODO: support for other filters/sorting?

			if ($this->isPost())
			{
				// redirect to give a linkable page
				return $this->redirect($this->buildLink('logs/admin', null, $linkFilters));
			}

			$viewParams = [
				'entries' => $logFinder->fetch(),
				'logUsers' => $adminLogRepo->getUsersInLog(),

				'userId' => $userId,

				'page' => $page,
				'perPage' => $perPage,
				'total' => $logFinder->total(),
				'linkFilters' => $linkFilters,
			];
			return $this->view('XF:Log\Admin\Listing', 'log_admin_list', $viewParams);
		}
	}

	public function actionSpamCleaner()
	{
		$page = $this->filterPage();
		$perPage = 20;

		/** @var SpamRepository $spamRepo */
		$spamRepo = $this->repository(SpamRepository::class);

		$logFinder = $spamRepo->findSpamCleanerLogsForList()
			->limitByPage($page, $perPage);

		$viewParams = [
			'entries' => $logFinder->fetch(),

			'page' => $page,
			'perPage' => $perPage,
			'total' => $logFinder->total(),
		];
		return $this->view('XF:Log\SpamCleaner\Listing', 'log_spam_cleaner_list', $viewParams);
	}

	public function actionSpamCleanerRestore(ParameterBag $params)
	{
		$entry = $this->assertSpamCleanerLogExists($params->spam_cleaner_log_id, null, 'requested_log_entry_not_found');

		if ($this->isPost())
		{
			$restorer = $this->app->spam()->restorer($entry);

			$restorer->restoreContent();
			$restorer->liftBan();
			$restorer->finalize();

			return $this->redirect($this->getDynamicRedirect());
		}
		else
		{
			$viewParams = [
				'entry' => $entry,
			];
			return $this->view('XF:Log\SpamCleaner\Restore', 'log_spam_cleaner_restore', $viewParams);
		}
	}

	public function actionSpamTrigger(ParameterBag $params)
	{
		if ($params->trigger_log_id)
		{
			return $this->rerouteController(self::class, 'spamTriggerView', $params);
		}

		$page = $this->filterPage();
		$perPage = 20;

		/** @var SpamRepository $spamRepo */
		$spamRepo = $this->repository(SpamRepository::class);

		$logFinder = $spamRepo->findSpamTriggerLogsForList()
			->limitByPage($page, $perPage);

		$viewParams = [
			'entries' => $logFinder->fetch(),

			'page' => $page,
			'perPage' => $perPage,
			'total' => $logFinder->total(),
		];
		return $this->view('XF:Log\SpamTrigger\Listing', 'log_spam_trigger_list', $viewParams);
	}

	public function actionSpamTriggerView(ParameterBag $params)
	{
		$entry = $this->assertSpamTriggerLogExists($params->trigger_log_id, null, 'requested_log_entry_not_found');

		$viewParams = [
			'entry' => $entry,
		];
		return $this->view('XF:Log\SpamTrigger\View', 'log_spam_trigger_view', $viewParams);
	}

	protected function applyLinkProxyFilters(Finder $finder, &$filters)
	{
		$filters = [];

		$url = $this->filter('url', 'str');
		$order = $this->filter('order', 'str');

		if ($url !== '')
		{
			$finder->where('url', 'like', $finder->escapeLike($url, '%?%'));
			$filters['url'] = $url;
		}

		switch ($order)
		{
			case 'first_request_date':
			case 'hits':
				$finder->order($order, 'DESC');
				$filters['order'] = $order;
		}
	}

	public function actionLinkProxy(ParameterBag $params)
	{
		if ($params->link_id)
		{
			return $this->rerouteController(self::class, 'linkProxyView', $params);
		}

		$page = $this->filterPage();
		$perPage = 20;

		/** @var LinkProxyRepository $proxyRepo */
		$proxyRepo = $this->repository(LinkProxyRepository::class);

		$logFinder = $proxyRepo->findLinkProxyLogsForList()
			->limitByPage($page, $perPage);

		$this->applyLinkProxyFilters($logFinder, $filters);

		$viewParams = [
			'entries' => $logFinder->fetch(),

			'page' => $page,
			'perPage' => $perPage,
			'total' => $logFinder->total(),

			'filters' => $filters,
		];
		return $this->view('XF:Log\LinkProxy\Listing', 'log_link_proxy_list', $viewParams);
	}

	public function actionLinkProxyView(ParameterBag $params)
	{
		$link = $this->assertLinkProxyExists($params->link_id);

		$viewParams = [
			'link' => $link,
		];
		return $this->view('XF:Log\LinkProxy\View', 'log_link_proxy_view', $viewParams);
	}

	protected function applyImageProxyFilters(Finder $finder, &$filters)
	{
		$filters = [];

		$url = $this->filter('url', 'str');
		$order = $this->filter('order', 'str');

		if ($url !== '')
		{
			$finder->where('url', 'like', $finder->escapeLike($url, '%?%'));
			$filters['url'] = $url;
		}

		switch ($order)
		{
			case 'first_request_date':
			case 'views':
			case 'file_size':
				$finder->order($order, 'DESC');
				$filters['order'] = $order;
		}
	}

	public function actionImageProxy(ParameterBag $params)
	{
		if ($params->image_id)
		{
			return $this->rerouteController(self::class, 'imageProxyView', $params);
		}

		$page = $this->filterPage();
		$perPage = 20;

		/** @var ImageProxyRepository $proxyRepo */
		$proxyRepo = $this->repository(ImageProxyRepository::class);

		$logFinder = $proxyRepo->findImageProxyLogsForList()
			->limitByPage($page, $perPage);

		$this->applyImageProxyFilters($logFinder, $filters);

		$viewParams = [
			'entries' => $logFinder->fetch(),

			'page' => $page,
			'perPage' => $perPage,
			'total' => $logFinder->total(),

			'filters' => $filters,
		];
		return $this->view('XF:Log\ImageProxy\Listing', 'log_image_proxy_list', $viewParams);
	}

	public function actionImageProxyImage(ParameterBag $params)
	{
		$image = $this->assertImageProxyExists($params->image_id);

		if (!$image->isValid() || $image->isRefreshRequired())
		{
			/** @var ImageProxyService $proxyService */
			$proxyService = $this->service(ImageProxyService::class);
			$image = $proxyService->refetchImage($image);
		}

		$proxyRepo = $this->repository(ImageProxyRepository::class);
		$placeHolderImage = $proxyRepo->getPlaceholderImage();

		if (!$image->isValid())
		{
			$image = $placeHolderImage;
		}

		$this->setResponseType('raw');

		$viewParams = [
			'image' => $image,
			'placeHolderImage' => $placeHolderImage,
		];
		return $this->view('XF:Log\ImageProxy\Image', '', $viewParams);
	}

	public function actionImageProxyView(ParameterBag $params)
	{
		$image = $this->assertImageProxyExists($params->image_id);

		$viewParams = [
			'image' => $image,
		];
		return $this->view('XF:Log\ImageProxy\View', 'log_image_proxy_view', $viewParams);
	}

	protected function applyOembedFilters(Finder $finder, &$filters)
	{
		$filters = [];

		$mediaSiteId = $this->filter('mediaSiteId', 'str');
		$order = $this->filter('order', 'str');

		if ($mediaSiteId !== '')
		{
			$finder->where('media_site_id', $mediaSiteId);
			$filters['mediaSiteId'] = $mediaSiteId;
		}

		switch ($order)
		{
			case 'first_request_date':
			case 'views':
			case 'file_size':
				$finder->order($order, 'DESC');
				$filters['order'] = $order;
		}
	}

	public function actionOembed(ParameterBag $params)
	{
		if ($params->oembed_id)
		{
			return $this->rerouteController(self::class, 'oEmbedView', $params);
		}

		$page = $this->filterPage();
		$perPage = 20;

		/** @var OembedRepository $oEmbedRepo */
		$oEmbedRepo = $this->repository(OembedRepository::class);

		$logFinder = $oEmbedRepo->findOembedLogsForList()
			->limitByPage($page, $perPage);

		$this->applyOembedFilters($logFinder, $filters);

		$mediaSites = $oEmbedRepo->findOembedMediaSitesForList()->fetch();

		$viewParams = [
			'entries' => $logFinder->fetch(),

			'page' => $page,
			'perPage' => $perPage,
			'total' => $logFinder->total(),

			'filters' => $filters,

			'mediaSites' => $mediaSites,
		];
		return $this->view('XF:Log\Oembed\Listing', 'log_oembed_list', $viewParams);
	}

	public function actionOembedView(ParameterBag $params)
	{
		$oEmbed = $this->assertOembedExists($params->oembed_id);

		if (!$oEmbed->isValid() || $oEmbed->isRefreshRequired())
		{
			/** @var OembedService $oEmbedService */
			$oEmbedService = $this->service(OembedService::class);
			$oEmbed = $oEmbedService->refetchOembed($oEmbed);
		}

		if ($oEmbed->isValid())
		{
			$oEmbedJson = json_decode($this->app->fs()->read($oEmbed->getAbstractedJsonPath()), true);
			$html = $oEmbedJson['html'];
			$providerJs = $oEmbed->media_site_id;

			if ($oEmbed->media_site_id == 'reddit' && preg_match('#\w+/comments/[A-Z0-9]+/\w+/[A-Z0-9]#si', $oEmbed->media_id))
			{
				$providerJs = 'reddit_comment';
			}
		}
		else
		{
			$html = '';
			$providerJs = null;
		}

		$viewParams = [
			'oembed' => $oEmbed,
			'html' => $html,
			'providerJs' => $providerJs,
		];
		return $this->view('XF:Log\Oembed\View', 'log_oembed_view', $viewParams);
	}

	public function actionSitemap()
	{
		/** @var SitemapLogRepository $sitemapRepo */
		$sitemapRepo = $this->repository(SitemapLogRepository::class);

		$viewParams = [
			'entries' => $sitemapRepo->findSitemapLogsForList()->fetch(),
		];
		return $this->view('XF:Log\Sitemap\Listing', 'log_sitemap_list', $viewParams);
	}

	public function actionPaymentProvider(ParameterBag $params)
	{
		if ($params->provider_log_id)
		{
			$entry = $this->assertPaymentProviderLogExists($params->provider_log_id, [
				'Provider',
				'PurchaseRequest',
				'PurchaseRequest.PaymentProfile',
				'PurchaseRequest.Purchasable',
				'PurchaseRequest.User',
			], 'requested_log_entry_not_found');

			$provider = $entry->Provider;
			$purchaseRequest = $entry->PurchaseRequest;
			if ($purchaseRequest)
			{
				$purchasable = $entry->PurchaseRequest->Purchasable;
				$purchasableItem = $purchasable->handler->getPurchasableFromExtraData($purchaseRequest->extra_data);
			}
			else
			{
				$purchasable = null;
				$purchasableItem = null;
			}

			$viewParams = [
				'entry' => $entry,
				'provider' => $provider,
				'purchaseRequest' => $purchaseRequest,
				'purchasable' => $purchasable,
				'purchasableItem' => $purchasableItem,
			];
			return $this->view('XF:Log\PaymentProvider\View', 'log_payment_provider_view', $viewParams);
		}
		else
		{
			$page = $this->filterPage();
			$perPage = 20;

			$entries = $this->finder(PaymentProviderLogFinder::class)
				->with('Provider', true)
				->with([
					'PurchaseRequest.PaymentProfile',
					'PurchaseRequest.Purchasable',
					'PurchaseRequest.User',
				])
				->order('provider_log_id', 'DESC')
				->limitByPage($page, $perPage);

			$linkParams = $this->filter([
				'purchase_request_key' => 'str',
				'transaction_id' => 'str',
				'subscriber_id' => 'str',
				'username' => 'str',
				'user_id' => 'uint',
				'payment_profile_id' => 'uint',
				'purchasable_type_id' => 'str',
			]);
			if ($linkParams['purchase_request_key'])
			{
				$entries->where('purchase_request_key', $linkParams['purchase_request_key']);
			}
			if ($linkParams['transaction_id'])
			{
				$entries->where('transaction_id', $linkParams['transaction_id']);
			}
			if ($linkParams['subscriber_id'])
			{
				$entries->where('subscriber_id', $linkParams['subscriber_id']);
			}
			if ($linkParams['username'])
			{
				$user = $this->em()->findOne(User::class, ['username' => $linkParams['username']]);
				if (!$user)
				{
					return $this->error(\XF::phrase('requested_user_not_found'));
				}
				$entries->where('PurchaseRequest.user_id', $user->user_id);
				unset($linkParams['username']);
				$linkParams['user_id'] = $user->user_id;
			}
			if ($linkParams['user_id'])
			{
				$user = $this->em()->find(User::class, $linkParams['user_id']);
				if (!$user)
				{
					return $this->error(\XF::phrase('requested_user_not_found'));
				}
				$entries->where('PurchaseRequest.user_id', $user->user_id);
			}
			if ($linkParams['payment_profile_id'])
			{
				$entries->where('PurchaseRequest.payment_profile_id', $linkParams['payment_profile_id']);
			}
			if ($linkParams['purchasable_type_id'])
			{
				$entries->where('PurchaseRequest.purchasable_type_id', $linkParams['purchasable_type_id']);
			}
			$linkParams = array_filter($linkParams);

			if ($this->isPost())
			{
				// Redirect to GET
				return $this->redirect($this->buildLink('logs/payment-provider', null, $linkParams));
			}

			$viewParams = [
				'entries' => $entries->fetch(),

				'page' => $page,
				'perPage' => $perPage,
				'total' => $entries->total(),
				'linkParams' => $linkParams,
			];
			return $this->view('XF:Log\PaymentProvider\Listing', 'log_payment_provider_list', $viewParams);
		}
	}

	public function actionPaymentProviderSearch(ParameterBag $params)
	{
		$profileFinder = $this->finder(PaymentProfileFinder::class);
		$profiles = $profileFinder
			->order('title')
			->fetch()
			->pluckNamed('title', 'payment_profile_id');

		$purchasableFinder = $this->finder(PurchasableFinder::class);
		$purchasables = $purchasableFinder
			->order('purchasable_type_id')
			->fetch()
			->pluckNamed('title', 'purchasable_type_id');

		$viewParams = [
			'profiles' => $profiles,
			'purchasables' => $purchasables,
		];
		return $this->view('XF:Log\PaymentProvider\Search', 'log_payment_provider_search', $viewParams);
	}

	public function actionUserChange()
	{
		$this->setSectionContext('userChangeLog');

		$page = $this->filterPage();
		$perPage = 20;

		$changeRepo = $this->repository(ChangeLogRepository::class);
		$changeFinder = $changeRepo->findChangeLogsByContentType('user')->limitByPage($page, $perPage);

		if ($username = $this->filter('username', 'str'))
		{
			$limitUser = $this->em()->findOne(User::class, ['username' => $username]);
			if (!$limitUser)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}
		}
		else if ($userId = $this->filter('edit_user_id', 'uint'))
		{
			$limitUser = $this->em()->find(User::class, $userId);
			if (!$limitUser)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}
		}
		else
		{
			$limitUser = null;
		}

		$linkFilters = [];
		if ($limitUser)
		{
			$linkFilters['edit_user_id'] = $limitUser->user_id;
			$changeFinder->where('edit_user_id', $limitUser->user_id);
		}

		$changes = $changeFinder->fetch();
		$changeRepo->addDataToLogs($changes);

		$viewParams = [
			'changesGrouped' => $changeRepo->groupChangeLogs($changes),
			'totalChanges' => count($changes),
			'limitUser' => $limitUser,

			'page' => $page,
			'perPage' => $perPage,
			'total' => $changeFinder->total(),
			'linkFilters' => $linkFilters,
		];
		return $this->view('XF:Log\UserChangeLog\Listing', 'log_user_change_list', $viewParams);
	}

	public function actionUsernameChange(ParameterBag $params)
	{
		if ($params->change_id)
		{
			$entry = $this->assertUsernameChangeLogExists($params->change_id, ['Moderator', 'ChangeUser']);

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view('XF:Log\UsernameChangeLog\View', 'log_username_change_view', $viewParams);
		}
		else
		{
			$page = $this->filterPage();
			$perPage = 20;

			$usernameChangeRepo = $this->repository(UsernameChangeRepository::class);
			$entryFinder = $usernameChangeRepo->findUsernameChangesForList()
				->limitByPage($page, $perPage);

			$entries = $entryFinder->fetch();

			$viewParams = [
				'entries' => $entries,

				'page' => $page,
				'perPage' => $perPage,
				'total' => $entryFinder->total(),
			];
			return $this->view('XF:Log\UsernameChangeLog\Listing', 'log_username_change_list', $viewParams);
		}
	}

	public function actionUsernameChangeToggle()
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle(UsernameChangeFinder::class, 'visible');
	}

	public function actionCookieConsent(ParameterBag $params): AbstractReply
	{
		if ($params->cookie_consent_log_id)
		{
			$entry = $this->assertCookieConsentLogExists(
				$params->cookie_consent_log_id,
				null,
				'requested_log_entry_not_found'
			);

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view(
				'XF:Log\CookieConsent\View',
				'log_cookie_consent_view',
				$viewParams
			);
		}

		$cookieConsentRepo = $this->repository(CookieConsentRepository::class);

		$page = $this->filterPage();
		$perPage = 20;

		$logFinder = $cookieConsentRepo->findLogsForList()
			->limitByPage($page, $perPage);

		if ($username = $this->filter('username', 'str'))
		{
			$limitUser = $this->em()->findOne(
				UserFinder::class,
				['username' => $username]
			);
			if (!$limitUser)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}
		}
		else if ($userId = $this->filter('user_id', 'uint'))
		{
			$limitUser = $this->em()->find(User::class, $userId);
			if (!$limitUser)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}
		}
		else
		{
			$limitUser = null;
		}

		if ($limitIp = $this->filter('ip', 'str'))
		{
			$parsedIp = Ip::parseIpRangeString($limitIp);
			if (!$parsedIp)
			{
				return $this->message(
					\XF::phrase('please_enter_valid_ip_or_ip_range')
				);
			}
		}
		else
		{
			$limitIp = null;
			$parsedIp = null;
		}

		$linkFilters = [];
		if ($limitUser)
		{
			$linkFilters['user_id'] = $limitUser->user_id;
			$logFinder->where('user_id', $limitUser->user_id);
		}
		if ($limitIp)
		{
			$linkFilters['ip'] = $limitIp;

			$startRange = Ip::stringToBinary(
				$parsedIp['startRange']
			);
			$endRange = Ip::stringToBinary(
				$parsedIp['endRange']
			);
			if ($parsedIp['isRange'])
			{
				$logFinder->where('ip_address', '>=', $startRange);
				$logFinder->where('ip_address', '<=', $endRange);
				$logFinder->where(
					$logFinder->expression('LENGTH(%s)', 'ip_address'),
					'=',
					strlen($startRange)
				);
			}
			else
			{
				$logFinder->where('ip_address', $startRange);
			}
		}

		if ($this->isPost())
		{
			return $this->redirect(
				$this->buildLink('logs/cookie-consent', null, $linkFilters)
			);
		}

		$entries = $logFinder->fetch();
		$total = $logFinder->total();

		$viewParams = [
			'entries' => $entries,

			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,

			'limitUser' => $limitUser,
			'limitIp' => $limitIp,
			'linkFilters' => $linkFilters,
		];
		return $this->view(
			'XF:Log\CookieConsent\Listing',
			'log_cookie_consent_list',
			$viewParams
		);
	}

	public function actionEmailBounces()
	{
		$this->setSectionContext('emailBounceLog');

		$bounceId = $this->filter('bounce_id', 'uint');
		if ($bounceId)
		{
			$bounce = $this->em()->find(EmailBounceLog::class, $bounceId);
			if (!$bounce)
			{
				return $this->notFound();
			}

			$this->setResponseType('raw');

			$viewParams = [
				'bounce' => $bounce,
			];
			return $this->view('XF:Log\EmailBounce\View', '', $viewParams);
		}

		$page = $this->filterPage();
		$perPage = 20;

		/** @var EmailBounceRepository $bounceRepo */
		$bounceRepo = $this->repository(EmailBounceRepository::class);

		$finder = $bounceRepo->findEmailBounceLogsForList()->limitByPage($page, $perPage);

		$viewParams = [
			'bounces' => $finder->fetch(),
			'total' => $finder->total(),

			'page' => $page,
			'perPage' => $perPage,
		];
		return $this->view('XF:Log\EmailBounce\Listing', 'log_email_bounce_list', $viewParams);
	}

	public function actionRejectedUser(ParameterBag $params)
	{
		$this->setSectionContext('rejectedUserLog');

		if ($params->user_id)
		{
			$entry = $this->assertRejectedUserLogExists($params->user_id);

			$viewParams = [
				'entry' => $entry,
			];
			return $this->view('XF:Log\RejectedUsers\View', 'log_rejected_users_view', $viewParams);
		}
		else
		{
			/** @var UserRejectRepository $rejectRepo */
			$rejectRepo = $this->repository(UserRejectRepository::class);

			$page = $this->filterPage();
			$perPage = 20;

			$finder = $rejectRepo->findUserRejectionsForList()->limitByPage($page, $perPage);

			$viewParams = [
				'rejections' => $finder->fetch(),
				'total' => $finder->total(),

				'page' => $page,
				'perPage' => $perPage,
			];
			return $this->view('XF:Log\RejectedUsers\Listing', 'log_rejected_users_list', $viewParams);
		}
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return ErrorLog
	 */
	protected function assertErrorLogExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(ErrorLog::class, $id, $with, $phraseKey);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return ModeratorLog
	 */
	protected function assertModeratorLogExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(ModeratorLog::class, $id, $with, $phraseKey);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return AdminLog
	 */
	protected function assertAdminLogExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(AdminLog::class, $id, $with, $phraseKey);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return SpamCleanerLog
	 */
	protected function assertSpamCleanerLogExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(SpamCleanerLog::class, $id, $with, $phraseKey);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return SpamTriggerLog
	 */
	protected function assertSpamTriggerLogExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(SpamTriggerLog::class, $id, $with, $phraseKey);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return LinkProxy
	 */
	protected function assertLinkProxyExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(LinkProxy::class, $id, $with, $phraseKey);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return ImageProxy
	 */
	protected function assertImageProxyExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(ImageProxy::class, $id, $with, $phraseKey);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return Oembed
	 */
	protected function assertOembedExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(Oembed::class, $id, $with, $phraseKey);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return PaymentProviderLog
	 */
	protected function assertPaymentProviderLogExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(PaymentProviderLog::class, $id, $with, $phraseKey);
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return UserReject
	 */
	protected function assertRejectedUserLogExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(UserReject::class, $id, $with, $phraseKey);
	}

	/**
	 * @param      $id
	 * @param null $with
	 * @param null $phraseKey
	 *
	 * @return Entity|UsernameChange
	 * @throws Exception
	 */
	protected function assertUsernameChangeLogExists($id, $with = null, $phraseKey = null): UsernameChange
	{
		return $this->assertRecordExists(UsernameChange::class, $id, $with, $phraseKey);
	}

	/**
	 * @param array|string|null $with
	 * @param string|null $phraseKey
	 */
	protected function assertCookieConsentLogExists(
		int $id,
		$with = null,
		$phraseKey = null
	): CookieConsentLog
	{
		return $this->assertRecordExists(
			CookieConsentLog::class,
			$id,
			$with,
			$phraseKey
		);
	}

	/**
	 * @return ErrorLogRepository
	 */
	protected function getErrorLogRepo()
	{
		return $this->repository(ErrorLogRepository::class);
	}
}
