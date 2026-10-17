<?php

namespace XF\Job;

use XF\Repository\BanningRepository;
use XF\Repository\BbCodeMediaSiteRepository;
use XF\Repository\BbCodeRepository;
use XF\Repository\ClassExtensionRepository;
use XF\Repository\CodeEventListenerRepository;
use XF\Repository\ConnectedAccountRepository;
use XF\Repository\EditorRepository;
use XF\Repository\ForumTypeRepository;
use XF\Repository\NavigationRepository;
use XF\Repository\NodeTypeRepository;
use XF\Repository\NoticeRepository;
use XF\Repository\OAuthRepository;
use XF\Repository\OptionRepository;
use XF\Repository\PaymentRepository;
use XF\Repository\ReactionRepository;
use XF\Repository\RouteFilterRepository;
use XF\Repository\SmilieRepository;
use XF\Repository\StyleRepository;
use XF\Repository\ThreadFieldRepository;
use XF\Repository\ThreadPrefixRepository;
use XF\Repository\ThreadTypeRepository;
use XF\Repository\UserFieldRepository;
use XF\Repository\UserGroupRepository;
use XF\Repository\UserTitleLadderRepository;
use XF\Repository\WidgetRepository;

class CoreCacheRebuild extends AbstractJob
{
	protected $defaultData = [
	];

	public function run($maxRunTime)
	{
		\XF::repository(OptionRepository::class)->updateOption('jsLastUpdate', \XF::$time);

		$this->app->get('addon.dataManager')->rebuildActiveAddOnCache();
		\XF::repository(BanningRepository::class)->rebuildBannedEmailCache();
		\XF::repository(BanningRepository::class)->rebuildBannedIpCache();
		\XF::repository(BanningRepository::class)->rebuildDiscouragedIpCache();
		\XF::repository(BbCodeRepository::class)->rebuildBbCodeCache();
		\XF::repository(BbCodeMediaSiteRepository::class)->rebuildBbCodeMediaSiteCache();
		\XF::repository(ConnectedAccountRepository::class)->rebuildProviderCount();
		\XF::repository(ClassExtensionRepository::class)->rebuildExtensionCache();
		\XF::repository(CodeEventListenerRepository::class)->rebuildListenerCache();
		\XF::repository(EditorRepository::class)->rebuildEditorDropdownCache();
		\XF::repository(ForumTypeRepository::class)->rebuildForumTypeCache();
		\XF::repository(NavigationRepository::class)->rebuildNavigationCache();
		\XF::repository(NodeTypeRepository::class)->rebuildNodeTypeCache();
		\XF::repository(NoticeRepository::class)->rebuildNoticeCache();
		\XF::repository(OAuthRepository::class)->rebuildClientCount();
		\XF::repository(OptionRepository::class)->rebuildOptionCache();
		\XF::repository(PaymentRepository::class)->rebuildPaymentProviderCache();
		\XF::repository(ReactionRepository::class)->rebuildReactionCache();
		\XF::repository(ReactionRepository::class)->rebuildReactionSpriteCache();
		\XF::repository(RouteFilterRepository::class)->rebuildRouteFilterCache();
		\XF::repository(SmilieRepository::class)->rebuildSmilieCache();
		\XF::repository(SmilieRepository::class)->rebuildSmilieSpriteCache();
		\XF::repository(StyleRepository::class)->updateAllStylesLastModifiedDateLater();
		\XF::repository(ThreadFieldRepository::class)->rebuildFieldCache();
		\XF::repository(ThreadPrefixRepository::class)->rebuildPrefixCache();
		\XF::repository(ThreadTypeRepository::class)->rebuildThreadTypeCache();
		\XF::repository(UserFieldRepository::class)->rebuildFieldCache();
		\XF::repository(UserGroupRepository::class)->rebuildDisplayStyleCache();
		\XF::repository(UserGroupRepository::class)->rebuildUserBannerCache();
		\XF::repository(UserTitleLadderRepository::class)->rebuildLadderCache();
		\XF::repository(WidgetRepository::class)->rebuildWidgetCache();
		\XF::repository(WidgetRepository::class)->recompileWidgets();

		return $this->complete();
	}

	public function getStatusMessage()
	{
		$actionPhrase = \XF::phrase('rebuilding');
		$typePhrase = \XF::phrase('core_caches');
		return sprintf('%s... %s', $actionPhrase, $typePhrase);
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}
