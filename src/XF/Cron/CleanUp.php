<?php

namespace XF\Cron;

use XF\Repository\ActivityLogRepository;
use XF\Repository\AddOnRepository;
use XF\Repository\AdminLogRepository;
use XF\Repository\ApiRepository;
use XF\Repository\AttachmentRepository;
use XF\Repository\CaptchaQuestionRepository;
use XF\Repository\ChangeLogRepository;
use XF\Repository\CookieConsentRepository;
use XF\Repository\DraftRepository;
use XF\Repository\EditHistoryRepository;
use XF\Repository\FileCheckRepository;
use XF\Repository\FindNewRepository;
use XF\Repository\ForumRepository;
use XF\Repository\ImageProxyRepository;
use XF\Repository\IpRepository;
use XF\Repository\LinkProxyRepository;
use XF\Repository\LoginAttemptRepository;
use XF\Repository\ModeratorLogRepository;
use XF\Repository\NewsFeedRepository;
use XF\Repository\OAuthRepository;
use XF\Repository\OembedRepository;
use XF\Repository\PreRegActionRepository;
use XF\Repository\SearchRepository;
use XF\Repository\SessionActivityRepository;
use XF\Repository\SpamRepository;
use XF\Repository\TagRepository;
use XF\Repository\TemplateRepository;
use XF\Repository\TfaAttemptRepository;
use XF\Repository\ThreadRedirectRepository;
use XF\Repository\ThreadReplyBanRepository;
use XF\Repository\ThreadRepository;
use XF\Repository\TrendingContentRepository;
use XF\Repository\UpgradeCheckRepository;
use XF\Repository\UserAlertRepository;
use XF\Repository\UserChangeTempRepository;
use XF\Repository\UserConfirmationRepository;
use XF\Repository\UserRememberRepository;
use XF\Repository\UserTfaTrustedRepository;
use XF\Repository\UserUpgradeRepository;
use XF\Service\FloodCheckService;
use XF\Session\StorageInterface;
use XF\Util\File;

class CleanUp
{
	/**
	 * Clean up tasks that should be done daily. This task cannot be relied on
	 * to run daily, consistently.
	 */
	public static function runDailyCleanUp()
	{
		$app = \XF::app();

		/** @var ThreadRepository $threadRepo */
		$threadRepo = $app->repository(ThreadRepository::class);
		$threadRepo->pruneThreadReadLogs();

		/** @var ForumRepository $forumRepo */
		$forumRepo = $app->repository(ForumRepository::class);
		$forumRepo->pruneForumReadLogs();

		/** @var Template $templateRepo */
		$templateRepo = $app->repository(TemplateRepository::class);
		$templateRepo->pruneEditHistory();

		/** @var IpRepository $ipRepo */
		$ipRepo = $app->repository(IpRepository::class);
		$ipRepo->pruneIps();

		/** @var DraftRepository $draftRepo */
		$draftRepo = $app->repository(DraftRepository::class);
		$draftRepo->pruneDrafts();

		/** @var PreRegActionRepository $preRegActionRepo */
		$preRegActionRepo = $app->repository(PreRegActionRepository::class);
		$preRegActionRepo->pruneActions();

		/** @var SearchRepository $searchRepo */
		$searchRepo = $app->repository(SearchRepository::class);
		$searchRepo->pruneSearches();

		/** @var FindNewRepository $findNewRepo */
		$findNewRepo = $app->repository(FindNewRepository::class);
		$findNewRepo->pruneFindNewResults();

		/** @var ModeratorLogRepository $modLogRepo */
		$modLogRepo = $app->repository(ModeratorLogRepository::class);
		$modLogRepo->pruneModeratorLogs();

		/** @var AdminLogRepository $adminLogRepo */
		$adminLogRepo = $app->repository(AdminLogRepository::class);
		$adminLogRepo->pruneAdminLogs();

		/** @var CookieConsentRepository $cookieConsentRepo */
		$cookieConsentRepo = $app->repository(CookieConsentRepository::class);
		$cookieConsentRepo->pruneCookieConsentLogs();

		/** @var TagRepository $tagRepo */
		$tagRepo = $app->repository(TagRepository::class);
		$tagRepo->pruneTagResultsCache();

		/** @var UserTfaTrustedRepository $tfaTrustRepo */
		$tfaTrustRepo = $app->repository(UserTfaTrustedRepository::class);
		$tfaTrustRepo->pruneTrustedKeys();

		/** @var EditHistoryRepository $editHistoryRepo */
		$editHistoryRepo = $app->repository(EditHistoryRepository::class);
		$editHistoryRepo->pruneEditHistory();

		/** @var FileCheckRepository $fileCheckRepo */
		$fileCheckRepo = $app->repository(FileCheckRepository::class);
		$fileCheckRepo->pruneFileChecks();

		/** @var AddOnRepository $addOnRepo */
		$addOnRepo = $app->repository(AddOnRepository::class);
		$addOnRepo->cleanUpAddOnBatches();

		/** @var UpgradeCheckRepository $upgradeCheckRepo */
		$upgradeCheckRepo = $app->repository(UpgradeCheckRepository::class);
		$upgradeCheckRepo->pruneUpgradeChecks();

		$oAuthRepo = $app->repository(OAuthRepository::class);
		$oAuthRepo->pruneExpiredCodes();
		$oAuthRepo->pruneAuthRequests();

		$trendingContentRepo = $app->repository(TrendingContentRepository::class);
		$trendingContentRepo->pruneResults();
	}

	/**
	 * Clean up tasks that should be done hourly. This task cannot be relied on
	 * to run every hour, consistently.
	 */
	public static function runHourlyCleanUp()
	{
		$app = \XF::app();

		/** @var StorageInterface $publicSessionStorage */
		$publicSessionStorage = $app->container('session.public.storage');
		$publicSessionStorage->deleteExpiredSessions();

		/** @var StorageInterface $adminSessionStorage */
		$adminSessionStorage = $app->container('session.admin.storage');
		$adminSessionStorage->deleteExpiredSessions();

		/** @var SessionActivityRepository $activityRepo */
		$activityRepo = $app->repository(SessionActivityRepository::class);
		$activityRepo->updateUserLastActivityFromSession();
		$activityRepo->pruneExpiredActivityRecords();

		/** @var UserRememberRepository $rememberRepo */
		$rememberRepo = $app->repository(UserRememberRepository::class);
		$rememberRepo->pruneExpiredRememberRecords();

		/** @var CaptchaQuestionRepository $captchaQuestion */
		$captchaQuestion = $app->repository(CaptchaQuestionRepository::class);
		$captchaQuestion->cleanUpCaptchaLog();

		/** @var LoginAttemptRepository $loginRepo */
		$loginRepo = $app->repository(LoginAttemptRepository::class);
		$loginRepo->cleanUpLoginAttempts();

		/** @var TfaAttemptRepository $tfaAttemptRepo */
		$tfaAttemptRepo = $app->repository(TfaAttemptRepository::class);
		$tfaAttemptRepo->cleanUpTfaAttempts();

		/** @var UserConfirmationRepository $userConfirmationRepo */
		$userConfirmationRepo = $app->repository(UserConfirmationRepository::class);
		$userConfirmationRepo->cleanUpUserConfirmationRecords();

		/** @var AttachmentRepository $attachmentRepo */
		$attachmentRepo = $app->repository(AttachmentRepository::class);
		$attachmentRepo->deleteUnassociatedAttachments();
		$attachmentRepo->deleteUnusedAttachmentData();

		/** @var ApiRepository $apiRepo */
		$apiRepo = $app->repository(ApiRepository::class);
		$apiRepo->pruneAttachmentKeys();
		$apiRepo->pruneLoginTokens();

		/** @var UserAlertRepository $alertRepo */
		$alertRepo = $app->repository(UserAlertRepository::class);
		$alertRepo->pruneViewedAlerts();
		$alertRepo->pruneUnviewedAlerts();

		/** @var ThreadRedirectRepository $redirectRepo */
		$redirectRepo = $app->repository(ThreadRedirectRepository::class);
		$redirectRepo->pruneThreadRedirects();

		/** @var FloodCheckService $floodChecker */
		$floodChecker = $app->service(FloodCheckService::class);
		$floodChecker->pruneFloodCheckData();

		/** @var SpamRepository $spamRepo */
		$spamRepo = $app->repository(SpamRepository::class);
		$spamRepo->cleanUpRegistrationResultCache();
		$spamRepo->cleanupContentSpamCheck();
		$spamRepo->cleanupSpamTriggerLog();

		/** @var ImageProxyRepository $imageProxyRepo */
		$imageProxyRepo = $app->repository(ImageProxyRepository::class);
		$imageProxyRepo->pruneImageCache();
		$imageProxyRepo->pruneImageProxyLogs();
		$imageProxyRepo->pruneImageReferrerLogs();

		/** @var OembedRepository $oembedRepo */
		$oembedRepo = $app->repository(OembedRepository::class);
		$oembedRepo->pruneOembedCache();
		$oembedRepo->pruneOembedLogs();
		$oembedRepo->pruneOembedReferrerLogs();

		/** @var LinkProxyRepository $linkProxyRepo */
		$linkProxyRepo = $app->repository(LinkProxyRepository::class);
		$linkProxyRepo->pruneLinkProxyLogs();
		$linkProxyRepo->pruneLinkReferrerLogs();

		/** @var ThreadReplyBanRepository $threadReplyBanRepo */
		$threadReplyBanRepo = $app->repository(ThreadReplyBanRepository::class);
		$threadReplyBanRepo->cleanUpExpiredBans();

		/** @var NewsFeedRepository $newsFeedRepo */
		$newsFeedRepo = $app->repository(NewsFeedRepository::class);
		$newsFeedRepo->cleanUpNewsFeedItems();

		/** @var ActivityLogRepository $activityLogRepo */
		$activityLogRepo = $app->repository(ActivityLogRepository::class);
		$activityLogRepo->pruneLogs();

		/** @var ChangeLogRepository $changeLogRepo */
		$changeLogRepo = $app->repository(ChangeLogRepository::class);
		$changeLogRepo->pruneChangeLogs();

		File::cleanUpPersistentTempFiles();
	}

	/**
	 * Downgrades expired user upgrades.
	 */
	public static function runUserDowngrade()
	{
		$userUpgradeRepo = \XF::repository(UserUpgradeRepository::class);
		$userUpgradeRepo->downgradeExpiredUpgrades();
	}

	/**
	 * Expire temporary user changes.
	 */
	public static function expireTempUserChanges()
	{
		$userChangeRepo = \XF::repository(UserChangeTempRepository::class);
		$userChangeRepo->removeExpiredChanges();
	}
}
