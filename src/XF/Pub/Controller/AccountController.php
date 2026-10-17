<?php

namespace XF\Pub\Controller;

use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\EditorPlugin;
use XF\ControllerPlugin\LoginPlugin;
use XF\CustomField\Set;
use XF\Data\TimeZone;
use XF\Entity\ConnectedAccountProvider;
use XF\Entity\Notice;
use XF\Entity\OAuthClient;
use XF\Entity\Passkey;
use XF\Entity\PaymentProfile;
use XF\Entity\Purchasable;
use XF\Entity\ReactionContent;
use XF\Entity\TfaProvider;
use XF\Entity\User;
use XF\Entity\UserAlert;
use XF\Entity\UserConnectedAccount;
use XF\Entity\UsernameChange;
use XF\Entity\UserProfile;
use XF\Entity\UserTfa;
use XF\Finder\UserFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\Mvc\Reply\View;
use XF\Repository\BookmarkRepository;
use XF\Repository\ConnectedAccountRepository;
use XF\Repository\IpRepository;
use XF\Repository\LanguageRepository;
use XF\Repository\NoticeRepository;
use XF\Repository\OAuthRepository;
use XF\Repository\PasskeyRepository;
use XF\Repository\PaymentRepository;
use XF\Repository\ReactionRepository;
use XF\Repository\StyleRepository;
use XF\Repository\TfaRepository;
use XF\Repository\UserAlertRepository;
use XF\Repository\UserTfaTrustedRepository;
use XF\Repository\UserUpgradeRepository;
use XF\Service\Passkey\ManagerService;
use XF\Service\User\AvatarService;
use XF\Service\User\EmailChangeService;
use XF\Service\User\PasswordChangeService;
use XF\Service\User\PasswordResetService;
use XF\Service\User\ProfileBannerService;
use XF\Service\User\SignatureEditService;
use XF\Service\User\UsernameChangeService;
use XF\Tfa\AbstractProvider;
use XF\Tfa\Backup;
use XF\Validator\Gravatar;

use function boolval, count, is_array, strlen;

class AccountController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertRegistrationRequired();
	}

	public function actionIndex()
	{
		return $this->rerouteController(self::class, 'account-details');
	}

	protected function addAccountWrapperParams(View $view, $selected)
	{
		$view->setParam('pageSelected', $selected);
		return $view;
	}

	public function actionAccountDetails()
	{
		$visitor = \XF::visitor();

		if ($this->isPost())
		{
			if ($visitor->canEditProfile())
			{
				$this->accountDetailsSaveProcess($visitor)->run();
			}

			return $this->redirect($this->buildLink('account/account-details'));
		}
		else
		{
			$viewParams = [
				'pendingUsernameChange' => $visitor->PendingUsernameChange,
				'canChangeEmail' => $visitor->canChangeEmail(),
			];
			$view = $this->view('XF:Account\AccountDetails', 'account_details', $viewParams);
			return $this->addAccountWrapperParams($view, 'account_details');
		}
	}

	protected function accountDetailsSaveProcess(User $visitor)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'option' => [
				'receive_admin_email' => 'bool',
				'show_dob_year' => 'bool',
				'show_dob_date' => 'bool',
			],
			'profile' => [
				'location' => 'str',
				'website' => 'str',
			],
			'user' => [
				'custom_title' => 'str',
			],
			'dob_day' => 'uint',
			'dob_month' => 'uint',
			'dob_year' => 'uint',
			'enable_activity_summary_email' => 'bool',
		]);

		if (!$visitor->hasPermission('general', 'editCustomTitle'))
		{
			unset($input['user']['custom_title']);
		}

		$input['profile']['about'] = $this->plugin(EditorPlugin::class)->fromInput('about');

		$form->setup(function () use ($visitor, $input)
		{
			$visitor->toggleActivitySummaryEmail($input['enable_activity_summary_email']);
		});
		$form->basicEntitySave($visitor, $input['user']);

		$userOptions = $visitor->getRelationOrDefault('Option');
		$form->setupEntityInput($userOptions, $input['option']);

		/** @var UserProfile $userProfile */
		$userProfile = $visitor->getRelationOrDefault('Profile');
		$form->setup(function () use ($userProfile, $input)
		{
			if (!$userProfile['dob_day'] || !$userProfile['dob_month'] || !$userProfile['dob_year'])
			{
				$userProfile->setDob($input['dob_day'], $input['dob_month'], $input['dob_year']);
			}
		});
		$this->customFieldsSaveProcess($form, 'personal', $userProfile);
		$this->customFieldsSaveProcess($form, 'contact', $userProfile);
		$form->setupEntityInput($userProfile, $input['profile']);

		$form->validate(function (FormAction $form) use ($input, $visitor)
		{
			if ($input['profile']['about'] && $visitor->isSpamCheckRequired())
			{
				$checker = $this->app()->spam()->contentChecker();
				$checker->check($visitor, $input['profile']['about'], [
					'content_type' => 'user',
					'content_id' => $visitor->user_id,
				]);

				$decision = $checker->getFinalDecision();
				switch ($decision)
				{
					case 'moderated':
					case 'denied':
						$checker->logSpamTrigger('user_about', $visitor->user_id);
						$form->logError(\XF::phrase('your_content_cannot_be_submitted_try_later'));
						break;
				}
			}
		});

		$form->complete(function () use ($visitor)
		{
			/** @var IpRepository $ipRepo */
			$ipRepo = $this->repository(IpRepository::class);
			$ipRepo->logIp($visitor->user_id, $this->request->getIp(), 'user', $visitor->user_id, 'account_details_edit');
		});

		return $form;
	}

	protected function setupUsernameChange(): UsernameChangeService
	{
		/** @var UsernameChangeService $service */
		$service = $this->service(UsernameChangeService::class, \XF::visitor());

		$service->setNewUsername($this->filter('username', 'str'));

		$reason = $this->filter('change_reason', 'str');
		if ($this->options()->usernameChangeRequireReason && !strlen($reason))
		{
			throw $this->exception($this->error(\XF::phrase('please_provide_reason_for_this_username_change')));
		}
		$service->setChangeReason($reason);

		return $service;
	}

	public function actionUsername()
	{
		$visitor = \XF::visitor();

		if (!$visitor->canChangeUsername($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$changer = $this->setupUsernameChange();

			if (!$changer->validate($errors))
			{
				return $this->error($errors);
			}

			/** @var UsernameChange $usernameChange */
			$usernameChange = $changer->save();

			if ($usernameChange->change_state == 'approved')
			{
				return $this->redirect(
					$this->buildLink('account/account-details'),
					\XF::phrase('your_username_has_been_changed_successfully')
				);
			}
			else
			{
				return $this->redirect(
					$this->buildLink('account/account-details'),
					\XF::phrase('your_username_change_must_be_approved_by_moderator')
				);
			}
		}
		else
		{
			$view = $this->view('XF:Account\Username', 'account_username');
			return $this->addAccountWrapperParams($view, 'account_details');
		}
	}

	public function actionEmail()
	{
		$visitor = \XF::visitor();
		$auth = $visitor->Auth->getAuthenticationHandler();
		if (!$auth)
		{
			return $this->noPermission();
		}

		if (!$visitor->canChangeEmail($error))
		{
			if (!$error)
			{
				$error = \XF::phrase('your_email_may_not_be_changed_at_this_time');
			}
			return $this->error($error);
		}

		if ($this->isPost())
		{
			$this->emailSaveProcess($visitor)->run();

			return $this->redirect($this->buildLink('account/account-details'));
		}
		else
		{
			$viewParams = [
				'hasPassword' => $auth->hasPassword(),
			];
			$view = $this->view('XF:Account\Email', 'account_email', $viewParams);

			return $this->addAccountWrapperParams($view, 'account_details');
		}
	}

	protected function emailSaveProcess(User $visitor)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'email' => 'str',
			'password' => 'str',
		]);

		if ($input['email'] != $visitor->email || $visitor->user_state === 'email_bounce')
		{
			/** @var EmailChangeService $emailChange */
			$emailChange = $this->service(EmailChangeService::class, $visitor, $input['email']);

			$form->validate(function (FormAction $form) use ($visitor, $input, $emailChange)
			{
				if (!$visitor->authenticate($input['password']))
				{
					$form->logError(\XF::phrase('your_existing_password_is_not_correct'), 'visitor_password');
				}
				else if (!$emailChange->isValid($changeError))
				{
					$form->logError($changeError, 'email');
				}
				else if (!$emailChange->canChangeEmail($error))
				{
					if (!$error)
					{
						$error = \XF::phrase('your_email_may_not_be_changed_at_this_time');
					}
					$form->logError($error, 'email');
				}
			});
			$form->apply(function () use ($emailChange)
			{
				$emailChange->save();
			});
		}

		return $form;
	}

	public function actionSignature()
	{
		$visitor = \XF::visitor();
		if (!$visitor->canEditSignature())
		{
			return $this->noPermission();
		}

		$sigEditor = $this->service(SignatureEditService::class, $visitor);

		if ($this->isPost())
		{
			$signature = $this->plugin(EditorPlugin::class)->fromInput('signature');
			$this->signatureSaveProcess($sigEditor, $signature)->run();

			return $this->redirect($this->buildLink('account/signature'));
		}
		else
		{
			$viewParams = [
				'disabledButtons' => $sigEditor->getDisabledEditorButtons(),
			];
			$view = $this->view('XF:Account\Signature', 'account_signature', $viewParams);
			return $this->addAccountWrapperParams($view, 'signature');
		}
	}

	protected function signatureSaveProcess(SignatureEditService $sigEditor, $inputSignature)
	{
		$form = $this->formAction();

		$form->validate(function (FormAction $form) use ($sigEditor, $inputSignature)
		{
			if (!$sigEditor->setSignature($inputSignature, $errors))
			{
				$form->logErrors($errors);
			}
		});
		$form->apply(function () use ($sigEditor)
		{
			$sigEditor->save();
		});

		$visitor = \XF::visitor();
		$form->complete(function () use ($visitor)
		{
			/** @var IpRepository $ipRepo */
			$ipRepo = $this->repository(IpRepository::class);
			$ipRepo->logIp($visitor->user_id, $this->request->getIp(), 'user', $visitor->user_id, 'signature_edit');
		});

		return $form;
	}

	public function actionPrivacy()
	{
		if ($this->isPost())
		{
			$this->savePrivacyProcess(\XF::visitor())->run();
			return $this->redirect($this->buildLink('account/privacy'));
		}
		else
		{
			$view = $this->view('XF:Account\Privacy', 'account_privacy');
			return $this->addAccountWrapperParams($view, 'privacy');
		}
	}

	protected function savePrivacyProcess(User $visitor)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'user' => [
				'visible' => 'bool',
				'activity_visible' => 'bool',
			],
			'option' => [
				'receive_admin_email' => 'bool',
				'show_dob_date' => 'bool',
				'show_dob_year' => 'bool',
			],
			'privacy' => [
				'allow_view_profile' => 'str',
				'allow_post_profile' => 'str',
				'allow_receive_news_feed' => 'str',
				'allow_send_personal_conversation' => 'str',
				'allow_view_identities' => 'str',
			],
			'enable_activity_summary_email' => 'bool',
		]);

		$form->setup(function () use ($visitor, $input)
		{
			$visitor->toggleActivitySummaryEmail($input['enable_activity_summary_email']);
		});
		$form->basicEntitySave($visitor, $input['user']);

		$userOptions = $visitor->getRelationOrDefault('Option');
		$form->setupEntityInput($userOptions, $input['option']);

		$userPrivacy = $visitor->getRelationOrDefault('Privacy');
		$form->setupEntityInput($userPrivacy, $input['privacy']);

		$form->complete(function () use ($visitor)
		{
			/** @var IpRepository $ipRepo */
			$ipRepo = $this->repository(IpRepository::class);
			$ipRepo->logIp($visitor->user_id, $this->request->getIp(), 'user', $visitor->user_id, 'privacy_edit');
		});

		return $form;
	}

	public function actionPreferences()
	{
		if ($this->isPost())
		{
			$this->preferencesSaveProcess(\XF::visitor())->run();
			return $this->redirect($this->buildLink('account/preferences'));
		}
		else
		{
			$styles = $this->repository(StyleRepository::class)->getUserSelectableStyles();

			/** @var LanguageRepository $languageRepo */
			$languageRepo = $this->repository(LanguageRepository::class);

			/** @var TimeZone $tzData */
			$tzData = $this->data(TimeZone::class);

			$alertRepo = $this->repository(UserAlertRepository::class);

			$viewParams = [
				'styles' => $styles,
				'defaultStyle' => $styles[$this->app->options()->defaultStyleId] ?? [],

				'languages' => $languageRepo->getUserSelectableLanguages(),

				'timeZones' => $tzData->getTimeZoneOptions(),

				'alertOptOuts' => $alertRepo->getAlertOptOuts(),
			];

			$view = $this->view('XF:Account\Preferences', 'account_preferences', $viewParams);
			return $this->addAccountWrapperParams($view, 'preferences');
		}
	}

	protected function preferencesSaveProcess(User $visitor)
	{
		$form = $this->formAction();

		$input = $this->filter([
			'user' => [
				'style_id' => 'uint',
				'style_variation' => 'str',
				'language_id' => 'uint',
				'timezone' => 'str',
				'visible' => 'bool',
				'activity_visible' => 'bool',
			],
			'option' => [
				'creation_watch_state' => 'str',
				'interaction_watch_state' => 'str',
				'content_show_signature' => 'bool',
				'receive_admin_email' => 'bool',
				'email_on_conversation' => 'bool',
				'push_on_conversation' => 'bool',
			],
			'moderator' => [
				'notify_report' => 'bool',
				'notify_approval' => 'bool',
			],
			'restore_notices' => 'bool',
			'enable_activity_summary_email' => 'bool',
		]);

		$alertRepo = $this->repository(UserAlertRepository::class);
		$optOutActions = $alertRepo->getAlertOptOutActions();
		$alert = $this->filter('alert', 'array-bool');
		$push = $this->filter('push', 'array-bool');
		$pushShown = $this->filter('push_shown', 'array-bool');

		$alertOptOuts = [];
		$pushOptOuts = [];
		foreach (array_keys($optOutActions) AS $optOut)
		{
			if (empty($alert[$optOut]))
			{
				$alertOptOuts[$optOut] = $optOut;
			}
			if (empty($push[$optOut]) && isset($pushShown[$optOut]))
			{
				$pushOptOuts[$optOut] = $optOut;
			}
		}

		$input['option']['alert_optout'] = $alertOptOuts;

		if ($visitor->canUsePushNotifications())
		{
			$input['option']['push_optout'] = $pushOptOuts;
		}

		$form->setup(function () use ($visitor, $input)
		{
			$visitor->toggleActivitySummaryEmail($input['enable_activity_summary_email']);
		});
		$form->validate(function () use ($visitor, $input)
		{
			if (!$visitor->is_moderator)
			{
				unset($input['moderator']);
			}
		});
		$form->basicEntitySave($visitor, $input['user']);

		$userOptions = $visitor->getRelationOrDefault('Option');
		$form->setupEntityInput($userOptions, $input['option']);

		if ($visitor->is_moderator)
		{
			$form->basicEntitySave($visitor->getRelation('Moderator'), $input['moderator']);
		}

		$this->customFieldsSaveProcess($form, 'preferences');

		$form->apply(function () use ($input, $visitor)
		{
			if ($input['restore_notices'])
			{
				$this->repository(NoticeRepository::class)->restoreDismissedNotices($visitor);
				$this->session()->remove('dismissedNotices'); // force recache
			}
		});
		$form->complete(function () use ($visitor)
		{
			/** @var IpRepository $ipRepo */
			$ipRepo = $this->repository(IpRepository::class);
			$ipRepo->logIp($visitor->user_id, $this->request->getIp(), 'user', $visitor->user_id, 'preferences_edit');
		});

		return $form;
	}

	public function actionDismissNotice()
	{
		/** @var Notice $notice */
		$notice = $this->assertRecordExists(Notice::class, $this->filter('notice_id', 'uint'));

		if (!$notice->canDismissNotice($error))
		{
			return $this->error($error);
		}

		if ($this->isPost())
		{
			$this->repository(NoticeRepository::class)->dismissNotice($notice, \XF::visitor());
			$this->session()->remove('dismissedNotices'); // force recache
			return $this->redirect($this->getDynamicRedirect());
		}
		else
		{
			return $this->view('XF:Account\DismissNotice', 'notice_dismiss', ['notice' => $notice]);
		}
	}

	public function actionAvatar()
	{
		$visitor = \XF::visitor();
		if (!$visitor->canUploadAvatar())
		{
			return $this->noPermission();
		}

		if ($this->isPost())
		{
			$useCustom = $this->filter('use_custom', 'bool');

			/** @var AvatarService $avatarService */
			$avatarService = $this->service(AvatarService::class, $visitor);

			if ($this->filter('delete_avatar', 'bool'))
			{
				$avatarService->deleteAvatar();
			}
			else if ($useCustom)
			{
				$upload = $this->request->getFile('upload', false, false);
				if ($upload)
				{
					if (!$avatarService->setImageFromUpload($upload))
					{
						return $this->error($avatarService->getError());
					}

					if (!$avatarService->updateAvatar())
					{
						return $this->error(\XF::phrase('new_avatar_could_not_be_processed'));
					}
				}
				else if ($visitor->avatar_date)
				{
					// recrop existing avatar
					$cropX = round($this->filter('avatar_crop_x', 'unum'));
					$cropY = round($this->filter('avatar_crop_y', 'unum'));
					if ($cropX != $visitor->Profile->avatar_crop_x || $cropY != $visitor->Profile->avatar_crop_y)
					{
						$avatarService->setImageFromExisting();
						$avatarService->setCrop($cropX, $cropY);
						if (!$avatarService->updateAvatar())
						{
							return $this->error(\XF::phrase('new_avatar_could_not_be_processed'));
						}
					}
					else
					{
						$avatarService->removeGravatar();
					}
				}
			}
			else if ($this->options()->gravatarEnable)
			{
				$gravatar = $this->filter('gravatar', 'str');
				if ($this->filter('test_gravatar', 'str'))
				{
					$gravatarValidator = $this->app->validator(Gravatar::class);
					$gravatar = $gravatarValidator->coerceValue($gravatar);
					if (!$gravatarValidator->isValid($gravatar, $errorKey))
					{
						return $this->error($gravatarValidator->getPrintableErrorValue($errorKey));
					}


					$reply = $this->view('XF:Account\Avatar');
					$reply->setJsonParams([
						'gravatarTest' => $gravatar,
						'gravatarPreview' => $visitor->getGravatarUrl('m', $gravatar),
					]);
					return $reply;
				}

				if (!$avatarService->setGravatar($gravatar))
				{
					return $this->error($avatarService->getError());
				}
			}

			if ($this->filter('_xfWithData', 'bool'))
			{
				return $this->view('XF:Account\AvatarUpdate', '');
			}
			else
			{
				return $this->redirect($this->buildLink('account/avatar'));
			}
		}
		else
		{
			$viewParams = [
				'maxSize' => $this->app->container('avatarSizeMap')['m'],
				'maxDimension' => ($visitor->avatar_width > $visitor->avatar_height ? 'height' : 'width'),
				'x' => $visitor->Profile->avatar_crop_x,
				'y' => $visitor->Profile->avatar_crop_y,
			];
			$view = $this->view('XF:Account\Avatar', 'account_avatar', $viewParams);
			return $this->addAccountWrapperParams($view, 'account_details');
		}
	}

	public function actionBanner()
	{
		$visitor = \XF::visitor();
		if (!$visitor->canUploadProfileBanner())
		{
			return $this->noPermission();
		}

		if ($this->isPost())
		{
			/** @var ProfileBannerService $bannerService */
			$bannerService = $this->service(ProfileBannerService::class, $visitor);

			if ($this->filter('delete_banner', 'bool'))
			{
				$bannerService->deleteBanner();
			}
			else
			{
				$upload = $this->request->getFile('upload', false, false);
				if ($upload)
				{
					if (!$bannerService->setImageFromUpload($upload))
					{
						return $this->error($bannerService->getError());
					}

					if (!$bannerService->updateBanner())
					{
						return $this->error(\XF::phrase('new_banner_could_not_be_processed'));
					}
				}
				else if ($visitor->Profile->banner_date)
				{
					// reposition existing avatar
					$posY = $this->filter('banner_position_y', 'uint');
					if ($posY != $visitor->Profile->banner_position_y)
					{
						if (!$bannerService->setPosition($posY))
						{
							return $this->error(\XF::phrase('new_banner_could_not_be_processed'));
						}
					}
				}
			}

			if ($this->filter('_xfWithData', 'bool'))
			{
				return $this->view('XF:Account\BannerUpdate', '');
			}
			else
			{
				return $this->redirect($this->buildLink('account/banner'));
			}
		}
		else
		{
			$viewParams = [];
			$view = $this->view('XF:Account\Banner', 'account_banner', $viewParams);
			return $this->addAccountWrapperParams($view, 'account_details');
		}
	}

	public function actionFollowing()
	{
		$followingUsers = [];
		$visitor = \XF::visitor();
		if ($following = $visitor->Profile->following)
		{
			$followingUsers = $this->finder(UserFinder::class)
				->where('user_id', $following)
				->order('username')
				->fetch();
		}

		$viewParams = [
			'following' => $followingUsers,
		];
		$view = $this->view('XF:Account\Following', 'account_following', $viewParams);
		return $this->addAccountWrapperParams($view, 'following');
	}

	public function actionIgnored()
	{
		$visitor = \XF::visitor();
		if ($ignored = $visitor->Profile->ignored)
		{
			$ignoringUsers = $this->finder(UserFinder::class)
				->where('user_id', array_keys($ignored))
				->order('username')
				->fetch();
		}
		else
		{
			$ignoringUsers = $this->em()->getEmptyCollection();
		}

		$viewParams = [
			'ignoring' => $ignoringUsers,
		];
		$view = $this->view('XF:Account\Ignored', 'account_ignored', $viewParams);
		return $this->addAccountWrapperParams($view, 'ignored');
	}

	public function actionReactions()
	{
		$this->assertNotEmbeddedImageRequest();

		$visitor = \XF::visitor();

		/** @var ReactionRepository $reactionRepo */
		$reactionRepo = $this->repository(ReactionRepository::class);

		$page = $this->filterPage();
		$perPage = 20;

		$typeTotals = $reactionRepo->getUserReactionsTabSummary($visitor);
		$total = array_sum($typeTotals);
		$tabSummary = [0 => $total] + $typeTotals;

		$reactionFinder = $reactionRepo->findUserReactions($visitor)
			->limitByPage($page, $perPage, 1);

		$reactionId = $this->filter('reaction_id', 'uint');

		if ($reactionId)
		{
			$reactionFinder->where('reaction_id', $reactionId);
		}
		else
		{
			// showing all, but limit this to the keys that we got from the total to implicitly skip inactive entries
			$reactionFinder->where('reaction_id', array_keys($typeTotals));
		}

		/** @var ArrayCollection|ReactionContent[] $reactions */
		$reactions = $reactionFinder->fetch();
		$hasNext = count($reactions) > $perPage;
		$reactions = $reactions->slice(0, $perPage);

		$reactionRepo->addContentToReactions($reactions);
		$reactions = $reactions->filter(function (ReactionContent $reaction)
		{
			return $reaction->canView() && $reaction->isRenderable();
		});

		$this->assertValidPage($page, $perPage, $total, 'account/reactions');

		$viewParams = [
			'tabSummary' => $tabSummary,

			'activeReactionId' => $reactionId,
			'reactions' => $reactions,
			'hasNext' => $hasNext,
			'page' => $page,

			'listOnly' => $this->filter('list_only', 'bool'),
		];
		$view = $this->view('XF:Account\Reactions', 'account_reactions', $viewParams);
		return $this->addAccountWrapperParams($view, 'reactions');
	}

	public function actionSecurity()
	{
		$visitor = \XF::visitor();
		$auth = $visitor->Auth->getAuthenticationHandler();
		if (!$auth)
		{
			return $this->noPermission();
		}

		$redirect = $this->getDynamicRedirect($this->buildLink('account/security'), false);

		if ($this->isPost())
		{
			$passwordChange = $this->setupPasswordChange();
			if (!$passwordChange->isValid($error))
			{
				return $this->error($error);
			}

			$passwordChange->setInvalidateRememberKeys(false); // about to handle this
			$passwordChange->save();

			$this->plugin(LoginPlugin::class)->handleVisitorPasswordChange();

			return $this->redirect($redirect);
		}
		else
		{
			/** @var TfaRepository $tfaRepo */
			$tfaRepo = $this->repository(TfaRepository::class);
			$enabledProviders = [];
			$deprecatedProviders = [];

			$userId = $visitor->user_id;

			foreach ($tfaRepo->getValidProviderList($userId) AS $provider)
			{
				if ($provider->isEnabled($userId))
				{
					$enabledProviders[] = $provider->getTitle();
					if ($provider->isDeprecated())
					{
						$deprecatedProviders[] = $provider->getTitle();
					}
				}
			}

			$passkeys = $this->repository(PasskeyRepository::class)
				->findPasskeysForUser($visitor)
				->fetch();

			$viewParams = [
				'hasPassword' => $auth->hasPassword(),
				'tfaEnabled' => \XF::config('enableTfa'),
				'enabledTfaProviders' => $enabledProviders,
				'deprecatedProviders' => $deprecatedProviders,
				'isSecurityLocked' => boolval($visitor->security_lock),
				'passkeys' => $passkeys,
				'redirect' => $redirect,
			];

			$view = $this->view('XF:Account\Security', 'account_security', $viewParams);
			return $this->addAccountWrapperParams($view, 'security');
		}
	}

	/**
	 * @return PasswordChangeService
	 *
	 * @throws Exception
	 */
	protected function setupPasswordChange()
	{
		$input = $this->filter([
			'old_password' => 'str',
			'password' => 'str',
			'password_confirm' => 'str',
		]);

		$visitor = \XF::visitor();

		if (!$visitor->authenticate($input['old_password']))
		{
			throw $this->errorException(\XF::phrase('your_existing_password_is_not_correct'));
		}

		if ($input['password'] !== $input['password_confirm'])
		{
			throw $this->errorException(\XF::phrase('passwords_did_not_match'));
		}

		return $this->service(PasswordChangeService::class, $visitor, $input['password']);
	}

	public function actionPasskeyAdd()
	{
		if ($this->isPost())
		{
			$newPasskey = $this->service(ManagerService::class, $this->session());
			if (!$newPasskey->create($this->request(), $error))
			{
				$newPasskey->clearStateFromSession($this->session());
				return $this->error($error);
			}

			return $this->redirect($this->buildLink('account/security'));
		}

		$newPasskey = $this->service(ManagerService::class);
		$newPasskey->saveStateToSession($this->session());

		$passkeyRepo = $this->repository(PasskeyRepository::class);
		$existingCredentials = $passkeyRepo->getExistingCredentialsForUser();

		if ($this->filter('_xfWithData', 'bool'))
		{
			$visitor = \XF::visitor();

			$view = $this->view();
			$view->setJsonParams([
				'challenge' => $newPasskey->getChallenge(),
				'existingCredentials' => $existingCredentials,
				'rpName' => $this->options()->boardTitle,
				'userId' => $visitor->user_id,
				'userName' => $visitor->email,
				'userDisplayName' => $visitor->username,
			]);
			return $view;
		}
		else
		{
			$viewParams = [
				'newPasskey' => $newPasskey,
				'existingCredentials' => $existingCredentials,
			];

			$view = $this->view('XF:Account\PasskeyAdd', 'account_passkey_add', $viewParams);
			return $this->addAccountWrapperParams($view, 'security');
		}
	}

	public function actionPasskeyEdit(ParameterBag $params)
	{
		$passkey = $this->assertPasskeyExists($params->passkey_id);

		if ($this->isPost())
		{
			$passkey->name = $this->filter('name', 'str');
			$passkey->save();

			return $this->redirect($this->buildLink('account/security'));
		}

		$viewParams = [
			'passkey' => $passkey,
		];

		$view = $this->view('XF:Account\PasskeyEdit', 'account_passkey_edit', $viewParams);
		return $this->addAccountWrapperParams($view, 'security');
	}

	public function actionPasskeyDelete(ParameterBag $params)
	{
		$passkey = $this->assertPasskeyExists($params->passkey_id);

		return $this->plugin(DeletePlugin::class)->actionDelete(
			$passkey,
			$this->buildLink('account/passkeys/delete', $passkey),
			$this->buildLink('account/passkeys/edit', $passkey),
			$this->buildLink('account/security'),
			$passkey->name
		);
	}

	public function actionConnectedAccount()
	{
		$visitor = \XF::visitor();
		$auth = $visitor->Auth->getAuthenticationHandler();

		$providers = $this->getConnectedAccountRepo()->getUsableProviders();

		$viewParams = [
			'providers' => $providers,
			'hasPassword' => $auth->hasPassword(),
		];
		$view = $this->view('XF:Account\Connected', 'account_connected', $viewParams);
		return $this->addAccountWrapperParams($view, 'connected_account');
	}

	public function actionConnectedAccountDisassociate(ParameterBag $params)
	{
		$this->assertPostOnly();

		$visitor = \XF::visitor();
		$auth = $visitor->Auth->getAuthenticationHandler();
		if (!$auth)
		{
			return $this->noPermission();
		}

		$connectedAccounts = $visitor->ConnectedAccounts;

		$provider = $this->assertProviderExists($params->provider_id);
		$handler = $provider->getHandler();

		/** @var UserConnectedAccount $connectedAccount */
		$connectedAccount = $connectedAccounts[$provider->provider_id] ?? null;
		if ($this->filter('disassociate', 'bool') && $connectedAccount)
		{
			$totalConnected = $connectedAccounts->count();

			$connectedAccount->delete();

			if (!$auth->hasPassword() && $totalConnected <= 1)
			{
				$visitor->Auth->resetPassword();
				$this->plugin(LoginPlugin::class)->handleVisitorPasswordChange();
				$sendConfirmation = true;
			}
			else
			{
				$sendConfirmation = false;
			}

			$storageState = $handler->getStorageState($provider, $visitor);
			$storageState->clearToken();

			$profile = $visitor->getRelationOrDefault('Profile');
			$profileConnectedAccounts = $profile->connected_accounts;
			unset($profileConnectedAccounts[$provider->provider_id]);
			$profile->connected_accounts = $profileConnectedAccounts;

			$visitor->save();

			if ($sendConfirmation)
			{
				/** @var PasswordResetService $passwordConfirmation */
				$passwordConfirmation = $this->service(PasswordResetService::class, $visitor);
				$passwordConfirmation->triggerConfirmation();
			}
		}
		return $this->redirect($this->buildLink('account/connected-accounts'));
	}

	public function actionRequestPassword()
	{
		$visitor = \XF::visitor();
		$auth = $visitor->Auth->getAuthenticationHandler();
		if (!$auth)
		{
			return $this->noPermission();
		}

		if ($auth->hasPassword())
		{
			return $this->error(\XF::phrase('your_account_already_has_password'));
		}

		if ($this->isPost())
		{
			$visitor->Auth->resetPassword();
			$passwordConfirmation = $this->service(PasswordResetService::class, $visitor);
			if (!$passwordConfirmation->canTriggerConfirmation($error))
			{
				return $this->error($error);
			}

			$passwordConfirmation->triggerConfirmation();
			return $this->redirect($this->buildLink('account/security'));
		}
		else
		{
			$view = $this->view('XF:Account\RequestPassword', 'account_request_password');
			return $this->addAccountWrapperParams($view, 'security');
		}
	}

	public function actionTwoStep()
	{
		$this->assertTfaEnabled();
		$this->assertTwoStepPasswordVerified();

		/** @var TfaRepository $tfaRepo */
		$tfaRepo = $this->repository(TfaRepository::class);

		/** @var LoginPlugin $loginPlugin */
		$loginPlugin = $this->plugin(LoginPlugin::class);
		$currentTrustKey = $loginPlugin->getCurrentTrustKey();

		/** @var UserTfaTrustedRepository $tfaTrustRepo */
		$tfaTrustRepo = $this->repository(UserTfaTrustedRepository::class);

		$visitor = \XF::visitor();
		$userId = $visitor->user_id;

		$deprecatedProviders = [];
		$providers = $tfaRepo->getValidProviderList($userId);

		foreach ($providers AS $provider)
		{
			if ($provider->isEnabled() && $provider->isDeprecated())
			{
				$deprecatedProviders[] = $provider->getTitle();
			}
		}

		$viewParams = [
			'providers' => $providers,
			'deprecatedProviders' => $deprecatedProviders,
			'backupAdded' => $this->filter('backup', 'bool') && $visitor->Option->use_tfa,
			'currentTrustRecord' => $tfaTrustRepo->getTfaTrustRecord($userId, $currentTrustKey),
			'hasOtherTrusted' => $tfaTrustRepo->hasOtherTrustedDevices($userId, $currentTrustKey),
		];
		$view = $this->view('XF:Account\TwoStep', 'account_two_step', $viewParams);
		return $this->addAccountWrapperParams($view, 'security');
	}

	public function actionTwoStepBackupCodes()
	{
		$this->assertTfaEnabled();
		$this->assertTwoStepPasswordVerified();

		/** @var TfaProvider $provider */
		$provider = $this->em()->find(TfaProvider::class, 'backup');
		if (!$provider || !$provider->canManage())
		{
			return $this->redirect($this->buildLink('account/two-step'));
		}

		/** @var Backup $handler */
		$handler = $provider->handler;
		$userConfig = $provider->getUserProviderConfig();
		if (!$userConfig || empty($userConfig['codes']))
		{
			return $this->redirect($this->buildLink('account/two-step'));
		}

		$viewParams = [
			'codes' => $handler->formatCodesForDisplay($userConfig['codes']),
		];
		$view = $this->view('XF:Account\TwoStepBackupCodes', 'account_two_step_backup_codes', $viewParams);
		return $this->addAccountWrapperParams($view, 'security');
	}

	public function actionTwoStepAdd(ParameterBag $params)
	{
		$this->assertTfaEnabled();
		$this->assertTwoStepPasswordVerified();
		$this->assertPostOnly();

		/** @var TfaProvider $provider */
		$provider = $this->em()->find(TfaProvider::class, $params->provider_id);
		if (!$provider || !$provider->canEnable() || !$provider->canHaveMultiple())
		{
			return $this->redirect($this->buildLink('account/two-step'));
		}

		$visitor = \XF::visitor();

		/** @var AbstractProvider $handler */
		$handler = $provider->handler;
		if (!$handler->renderAdd())
		{
			return $this->redirect($this->buildLink('account/two-step'));
		}

		if (!$handler->meetsRequirements($visitor, $error))
		{
			return $this->error($error);
		}

		$sessionKey = 'tfaData_' . $provider->provider_id;
		$session = $this->session();

		$step = $this->filter('step', 'str');

		if ($step == 'confirm')
		{
			$providerData = $session->get($sessionKey);
			if (!is_array($providerData))
			{
				return $this->redirect($this->buildLink('account/two-step'));
			}

			if (!$handler->verify('setup', $visitor, $providerData, $this->request))
			{
				return $this->error(\XF::phrase('two_step_verification_value_could_not_be_confirmed'));
			}

			/** @var TfaRepository $tfaRepo */
			$tfaRepo = $this->repository(TfaRepository::class);
			$tfaRepo->enableUserTfaProvider($visitor, $provider, $providerData, $backupAdded);

			/** @var IpRepository $ipRepo */
			$ipRepo = $this->repository(IpRepository::class);
			$ipRepo->logIp($visitor->user_id, $this->request->getIp(), 'user', $visitor->user_id, 'tfa_enable');

			$session->remove($sessionKey);

			if ($backupAdded)
			{
				return $this->redirect($this->buildLink('account/two-step', null, ['backup' => $backupAdded ? 1 : null]));
			}

			return $this->redirect($this->buildLink('account/two-step', null, ['backup' => $backupAdded ? 1 : null]));
		}

		$providerData = [];

		if ($handler->requiresConfig())
		{
			$result = $handler->handleAdd($this, $provider, \XF::visitor(), $providerData);
			if ($result)
			{
				if ($result instanceof View)
				{
					$result = $this->addAccountWrapperParams($result, 'security');
				}
			}
		}

		$providerData = $handler->generateInitialData($visitor, $providerData);
		$triggerData = $handler->trigger('setup', $visitor, $providerData, $this->request);

		$session->set($sessionKey, $providerData);

		$view = $handler->renderAdd($this, 'setup', $visitor, $providerData);
		return $this->addAccountWrapperParams($view, 'security');
	}

	public function actionTwoStepEnable(ParameterBag $params)
	{
		$this->assertTfaEnabled();
		$this->assertTwoStepPasswordVerified();
		$this->assertPostOnly();

		/** @var TfaProvider $provider */
		$provider = $this->em()->find(TfaProvider::class, $params->provider_id);
		if (!$provider || !$provider->canEnable())
		{
			return $this->redirect($this->buildLink('account/two-step'));
		}

		$visitor = \XF::visitor();

		/** @var AbstractProvider $handler */
		$handler = $provider->handler;
		if (!$handler->meetsRequirements($visitor, $error))
		{
			return $this->error($error);
		}

		$sessionKey = 'tfaData_' . $provider->provider_id;
		$session = $this->session();

		$step = $this->filter('step', 'str');

		if ($step == 'confirm')
		{
			$providerData = $session->get($sessionKey);
			if (!is_array($providerData))
			{
				return $this->redirect($this->buildLink('account/two-step'));
			}

			if (!$handler->verify('setup', $visitor, $providerData, $this->request))
			{
				return $this->error(\XF::phrase('two_step_verification_value_could_not_be_confirmed'));
			}

			/** @var TfaRepository $tfaRepo */
			$tfaRepo = $this->repository(TfaRepository::class);
			$tfaRepo->enableUserTfaProvider($visitor, $provider, $providerData, $backupAdded);

			/** @var IpRepository $ipRepo */
			$ipRepo = $this->repository(IpRepository::class);
			$ipRepo->logIp($visitor->user_id, $this->request->getIp(), 'user', $visitor->user_id, 'tfa_enable');

			$session->remove($sessionKey);

			return $this->redirect($this->buildLink('account/two-step', null, ['backup' => $backupAdded ? 1 : null]));
		}

		$providerData = [];

		if ($handler->requiresConfig())
		{
			$result = $handler->handleConfig($this, $provider, \XF::visitor(), $providerData);
			if ($result)
			{
				if ($result instanceof View)
				{
					$result = $this->addAccountWrapperParams($result, 'security');
				}
				return $result;
			}
		}

		$providerData = $handler->generateInitialData($visitor, $providerData);
		$triggerData = $handler->trigger('setup', $visitor, $providerData, $this->request);

		$session->set($sessionKey, $providerData);

		$viewParams = [
			'provider' => $provider,
			'handler' => $handler,
			'providerData' => $providerData,
			'triggerData' => $triggerData,
		];
		$view = $this->view('XF:Account\TwoStepEnable', 'account_two_step_enable', $viewParams);
		return $this->addAccountWrapperParams($view, 'security');
	}

	public function actionTwoStepDisable(ParameterBag $params)
	{
		$this->assertTfaEnabled();
		$this->assertTwoStepPasswordVerified();

		if ($params->provider_id)
		{
			/** @var TfaProvider $provider */
			$provider = $this->em()->find(TfaProvider::class, $params->provider_id);
			if (!$provider || !$provider->canDisable())
			{
				return $this->redirect($this->buildLink('account/two-step'));
			}
		}
		else
		{
			$provider = null;
		}

		if ($this->isPost())
		{
			$visitor = \XF::visitor();

			if ($provider)
			{
				/** @var UserTfa|null $userTfa */
				$userTfa = $provider->UserEntries[\XF::visitor()->user_id];
				if ($userTfa)
				{
					$userTfa->delete();
				}
			}
			else
			{
				/** @var TfaRepository $tfaRepo */
				$tfaRepo = $this->repository(TfaRepository::class);
				$tfaRepo->disableTfaForUser(\XF::visitor());
			}

			/** @var IpRepository $ipRepo */
			$ipRepo = $this->repository(IpRepository::class);
			$ipRepo->logIp($visitor->user_id, $this->request->getIp(), 'user', $visitor->user_id, 'tfa_disable');

			return $this->redirect($this->buildLink('account/two-step'));
		}
		else
		{
			$viewParams = [
				'provider' => $provider,
			];
			$view = $this->view('XF:Account\TwoStepDisable', 'account_two_step_disable', $viewParams);
			return $this->addAccountWrapperParams($view, 'security');
		}
	}

	public function actionTwoStepManage(ParameterBag $params)
	{
		$this->assertTfaEnabled();
		$this->assertTwoStepPasswordVerified();

		/** @var TfaProvider $provider */
		$provider = $this->em()->find(TfaProvider::class, $params->provider_id);
		if (!$provider || !$provider->canManage())
		{
			return $this->redirect($this->buildLink('account/two-step'));
		}

		/** @var AbstractProvider $handler */
		$handler = $provider->handler;
		$result = $handler->handleManage($this, $provider, \XF::visitor(), $provider->getUserProviderConfig());
		if (!$result)
		{
			return $this->redirect($this->buildLink('account/two-step'));
		}

		if ($result instanceof View)
		{
			$result = $this->addAccountWrapperParams($result, 'security');
		}
		return $result;
	}

	public function actionTwoStepTrustedDisable()
	{
		$this->assertTfaEnabled();
		$this->assertPostOnly();
		$this->assertTwoStepPasswordVerified();

		/** @var UserTfaTrustedRepository $tfaTrustRepo */
		$tfaTrustRepo = $this->repository(UserTfaTrustedRepository::class);

		/** @var LoginPlugin $loginPlugin */
		$loginPlugin = $this->plugin(LoginPlugin::class);
		$currentTrustKey = $loginPlugin->getCurrentTrustKey();

		$userId = \XF::visitor()->user_id;

		if ($this->filter('others', 'bool'))
		{
			$tfaTrustRepo->untrustOtherDevices($userId, $currentTrustKey);
		}
		else
		{
			$tfaTrustRepo->untrustDevice($userId, $currentTrustKey);
		}

		return $this->redirect($this->buildLink('account/two-step'));
	}

	protected function assertTwoStepPasswordVerified()
	{
		$this->assertPasswordVerified(3600, null, function ($view)
		{
			return $this->addAccountWrapperParams($view, 'security');
		});
	}

	public function actionUpgrades()
	{
		$purchasable = $this->em()->find(Purchasable::class, 'user_upgrade', 'AddOn');
		if (!$purchasable->isActive())
		{
			return $this->message(\XF::phrase('no_account_upgrades_can_be_purchased_at_this_time'));
		}

		$upgradeRepo = $this->repository(UserUpgradeRepository::class);
		[$available, $purchased] = $upgradeRepo->getFilteredUserUpgradesForList();

		if (!$available && !$purchased)
		{
			return $this->message(\XF::phrase('no_account_upgrades_can_be_purchased_at_this_time'));
		}

		if (\XF::visitor()->user_state != 'valid')
		{
			return $this->error(\XF::phrase('account_upgrades_cannot_be_purchased_account_unconfirmed'));
		}

		$profileIds = [];
		foreach ($available AS $upgrade)
		{
			foreach ($upgrade->payment_profile_ids AS $profileId)
			{
				$profileIds[$profileId] = true;
			}
		}

		$paymentRepo = $this->repository(PaymentRepository::class);
		/** @var AbstractCollection|PaymentProfile[] $profiles */
		$profiles = $paymentRepo->findPaymentProfilesForList()->fetch();
		$profileThirdParties = [];
		foreach ($profiles AS $profileId => $profile)
		{
			$profileUsed = $profileIds[$profileId] ?? false;
			if (!$profile->active || !$profileUsed)
			{
				unset($profiles[$profileId]);
				continue;
			}

			$profileThirdParties = array_merge(
				$profileThirdParties,
				$profile->Provider->getCookieThirdParties()
			);
		}

		$this->assertCookieConsent([], array_unique($profileThirdParties));

		$viewParams = [
			'available' => $available,
			'purchased' => $purchased,
			'profiles' => $profiles,
		];
		$view = $this->view('XF:Account\Upgrades', 'account_upgrades', $viewParams);
		return $this->addAccountWrapperParams($view, 'upgrades');
	}

	public function actionUpgradePurchase()
	{
		$view = $this->view('XF:Account\UpgradePurchase', 'account_upgrade_purchase');
		return $this->addAccountWrapperParams($view, 'upgrades');
	}

	public function actionUpgradeUpdated()
	{
		$view = $this->view('XF:Account\UpgradePurchase', 'account_upgrade_updated');
		return $this->addAccountWrapperParams($view, 'upgrades');
	}

	public function actionNewsFeed()
	{
		return $this->redirectPermanently(
			$this->buildLink('whats-new/news-feed')
		);
	}

	public function actionAlerts()
	{
		$this->assertNotEmbeddedImageRequest();

		$visitor = \XF::visitor();

		$page = $this->filterPage();
		$perPage = $this->options()->alertsPerPage;

		/** @var UserAlertRepository $alertRepo */
		$alertRepo = $this->repository(UserAlertRepository::class);

		$alertsFinder = $alertRepo->findAlertsForUser($visitor->user_id);

		$alerts = $alertsFinder->limitByPage($page, $perPage)->fetch();
		$alertRepo->addContentToAlerts($alerts);
		$this->markInaccessibleAlertsReadIfNeeded($alerts);
		$alerts = $alerts->filterViewable();

		$skipMarkRead = $this->filter('skip_mark_read', 'bool');
		if (!$skipMarkRead)
		{
			$alertRepo->autoMarkUserAlertsRead($alerts, $visitor);

			if ($page == 1 && $visitor->alerts_unviewed)
			{
				$alertRepo->markUserAlertsViewed($visitor);
			}
		}

		$viewParams = [
			'alerts' => $alerts,

			'page' => $page,
			'perPage' => $perPage,
			'totalAlerts' => $alertsFinder->total(),
		];
		$view = $this->view('XF:Account\Alerts', 'account_alerts', $viewParams);
		return $this->addAccountWrapperParams($view, 'alerts');
	}

	public function actionAlertsPopup()
	{
		$this->assertNotEmbeddedImageRequest();

		$visitor = \XF::visitor();

		/** @var UserAlertRepository $alertRepo */
		$alertRepo = $this->repository(UserAlertRepository::class);

		$cutOff = \XF::$time - $this->options()->alertsPopupExpiryDays * 86400;
		$alertsFinder = $alertRepo->findAlertsForUser($visitor->user_id, $cutOff);

		$alerts = $alertsFinder->fetch(25);
		$alertRepo->addContentToAlerts($alerts);
		$this->markInaccessibleAlertsReadIfNeeded($alerts);
		$alerts = $alerts->filterViewable();

		$alertRepo->autoMarkUserAlertsRead($alerts, $visitor);

		if ($visitor->alerts_unviewed)
		{
			$alertRepo->markUserAlertsViewed($visitor);
		}

		$viewParams = [
			'alerts' => $alerts,
		];
		return $this->view('XF:Account\AlertsPopup', 'account_alerts_popup', $viewParams);
	}

	protected function markInaccessibleAlertsReadIfNeeded(?AbstractCollection $displayedAlerts = null)
	{
		$visitor = \XF::visitor();

		if (!$visitor->alerts_unread)
		{
			return;
		}

		if ($displayedAlerts)
		{
			$hasInaccessibleUnread = false;
			$showingUnread = false;
			foreach ($displayedAlerts AS $alert)
			{
				/** @var UserAlert $alert */
				if ($alert->isUnread())
				{
					$showingUnread = true;

					if (!$alert->canView())
					{
						$hasInaccessibleUnread = true;
					}
				}
			}

			if ($showingUnread && !$hasInaccessibleUnread)
			{
				// If we have unread on this page, we know we're still going to have some unread alerts left.
				// However, if we detect an inaccessible alert, let's do a check on the alerts to try to
				// sort out anything that might still be stuck.
				return;
			}
		}

		/** @var UserAlertRepository $alertRepo */
		$alertRepo = $this->repository(UserAlertRepository::class);

		$alertRepo->markInaccessibleAlertsRead($visitor);
	}

	public function actionAlertsMarkRead()
	{
		$visitor = \XF::visitor();

		/** @var UserAlertRepository $alertRepo */
		$alertRepo = $this->repository(UserAlertRepository::class);

		$redirect = $this->getDynamicRedirect($this->buildLink('account/alerts'));

		if ($this->isPost())
		{
			$alertRepo->markUserAlertsRead($visitor);

			return $this->redirect($redirect, \XF::phrase('all_alerts_marked_as_read'));
		}
		else
		{
			$viewParams = [
				'redirect' => $redirect,
			];
			$view = $this->view('XF:Account\AlertsMarkRead', 'account_alerts_mark_read', $viewParams);
			return $this->addAccountWrapperParams($view, 'alerts');
		}
	}

	public function actionAlertToggle()
	{
		$alertId = $this->filter('alert_id', 'uint');
		$alert = $this->assertViewableAlert($alertId);

		/** @var UserAlertRepository $alertRepo */
		$alertRepo = $this->repository(UserAlertRepository::class);

		$newUnreadStatus = $this->filter('unread', '?bool');
		if ($newUnreadStatus === null)
		{
			$newUnreadStatus = $alert->isUnread() ? false : true;
		}

		$redirect = $this->getDynamicRedirect($this->buildLink('account/alerts'));

		if ($this->isPost())
		{
			if ($newUnreadStatus)
			{
				$alertRepo->markUserAlertUnread($alert);
				$message = \XF::phrase('alert_marked_as_unread');
			}
			else
			{
				$alertRepo->markUserAlertRead($alert);
				$message = \XF::phrase('alert_marked_as_read');
			}

			return $this->redirect($redirect, $message);
		}
		else
		{
			$viewParams = [
				'alert' => $alert,
				'newUnreadStatus' => $newUnreadStatus,
				'redirect' => $redirect,
			];
			$view = $this->view('XF:Account\AlertToggle', 'account_alert_toggle', $viewParams);
			return $this->addAccountWrapperParams($view, 'alerts');
		}
	}

	public function actionBookmarks()
	{
		$visitor = \XF::visitor();

		if (!$visitor->canViewBookmarks())
		{
			return $this->noPermission();
		}

		if ($this->isPost())
		{
			$label = $this->filter('label', 'str');
			return $this->redirect($this->buildLink('account/bookmarks', null, ['label' => $label]));
		}

		$page = $this->filterPage();
		$perPage = 20;

		/** @var BookmarkRepository $bookmarkRepo */
		$bookmarkRepo = $this->repository(BookmarkRepository::class);

		$label = $this->filter('label', 'str');
		if ($label)
		{
			$bookmarksFinder = $bookmarkRepo->findBookmarksForUserByLabel($visitor->user_id, $label);
		}
		else
		{
			$bookmarksFinder = $bookmarkRepo->findBookmarksForUser($visitor->user_id);
		}

		// quite a big over-fetch but should mostly avoid pagination
		// issues resulting from invisible content
		$bookmarks = $bookmarksFinder->limitByPage($page, $perPage, $perPage * 4)->fetch();

		$bookmarkRepo->addContentToBookmarks($bookmarks);
		$viewableBookmarks = $bookmarks->filterViewable();
		$difference = min($bookmarks->count(), 20) - min($viewableBookmarks->count(), 20);
		$bookmarks = $viewableBookmarks->slice($this->filter('difference', 'uint', 0), $perPage);

		$labelFinder = $bookmarkRepo->findLabelsForUser($visitor->user_id);
		$labels = $labelFinder->fetch()->pluckNamed('label', 'label');

		$viewParams = [
			'bookmarks' => $bookmarks,
			'label' => $label,
			'allLabels' => $labels,

			'page' => $page,
			'perPage' => $perPage,
			'totalBookmarks' => $bookmarksFinder->total(),

			'paginationDifference' => $difference,
		];
		$view = $this->view('XF:Account\Bookmarks', 'account_bookmarks', $viewParams);
		return $this->addAccountWrapperParams($view, 'bookmarks');
	}

	public function actionBookmarksPopup()
	{
		$visitor = \XF::visitor();

		if (!$visitor->canViewBookmarks())
		{
			return $this->noPermission();
		}

		/** @var BookmarkRepository $bookmarkRepo */
		$bookmarkRepo = $this->repository(BookmarkRepository::class);

		$label = $this->filter('label', 'str');
		if ($label)
		{
			$bookmarksFinder = $bookmarkRepo->findBookmarksForUserByLabel($visitor->user_id, $label);
		}
		else
		{
			$bookmarksFinder = $bookmarkRepo->findBookmarksForUser($visitor->user_id);
		}

		$bookmarks = $bookmarksFinder->fetch(25);

		$bookmarkRepo->addContentToBookmarks($bookmarks);
		$bookmarks = $bookmarks->filterViewable();

		$labelFinder = $bookmarkRepo->findLabelsForUser($visitor->user_id);
		$labels = $labelFinder->fetch()->pluckNamed('label', 'label');

		$viewParams = [
			'bookmarks' => $bookmarks,
			'label' => $label,
			'allLabels' => $labels,
		];
		$view = $this->view('XF:Account\BookmarksPopup', 'account_bookmarks_popup', $viewParams);

		$view->setJsonParam('showAllUrl', $this->buildLink('account/bookmarks', null, ['label' => $label]));

		return $view;
	}

	public function actionBookmarksAutoComplete()
	{
		$visitor = \XF::visitor();

		if (!$visitor->canViewBookmarks())
		{
			return $this->noPermission();
		}

		$q = $this->filter('q', 'str');

		if (strlen($q) >= 2)
		{
			$labels = $this->repository(BookmarkRepository::class)->getLabelAutoCompleteResults($q);

			$results = [];
			foreach ($labels AS $label)
			{
				$results[] = [
					'id' => $label->label,
					'text' => $label->label,
					'q' => $q,
				];
			}
		}
		else
		{
			$results = [];
		}
		$view = $this->view();
		$view->setJsonParam('results', $results);
		return $view;
	}

	public function actionApplications(ParameterBag $params)
	{
		if ($params->client_id)
		{
			return $this->rerouteController(self::class, 'applicationsView', $params);
		}

		$authRepo = $this->repository(OAuthRepository::class);
		$clients = $authRepo->getConnectedClientsForUser();

		$viewParams = [
			'clients' => $clients,
		];
		$view = $this->view('XF:Account\Applications\Index', 'account_applications', $viewParams);
		return $this->addAccountWrapperParams($view, 'applications');
	}

	public function actionApplicationsView(ParameterBag $params): View
	{
		$client = $this->assertRecordExists(OAuthClient::class, $params->client_id);

		$authRepo = $this->repository(OAuthRepository::class);
		$scopes = $authRepo->getScopesForTokens($client);

		$viewParams = [
			'client' => $client,
			'scopes' => $scopes,
		];
		$view = $this->view('XF:Account\Applications\View', 'account_applications_view', $viewParams);
		return $this->addAccountWrapperParams($view, 'applications');
	}

	public function actionApplicationsRevoke(ParameterBag $params)
	{
		$visitor = \XF::visitor();

		$client = $this->assertRecordExists(
			OAuthClient::class,
			$params->client_id
		);

		if ($this->isPost())
		{
			$authRepo = $this->repository(OAuthRepository::class);
			$authRepo->revokeClientForUser($client, $visitor);

			return $this->redirect($this->buildLink('account/applications'));
		}

		$viewParams = [
			'client' => $client,
		];

		return $this->view('XF:Account\Applications\Revoke', 'account_applications_revoke', $viewParams);
	}

	public function actionVisitorMenu()
	{
		$viewParams = [];
		return $this->view('XF:Account\VisitorMenu', 'account_visitor_menu', $viewParams);
	}

	protected function customFieldsSaveProcess(FormAction $form, $group, ?UserProfile $userProfile = null, $entitySave = false)
	{
		if ($userProfile === null)
		{
			$userProfile = \XF::visitor()->getRelationOrDefault('Profile');
		}

		/** @var Set $fieldSet */
		$fieldSet = $userProfile->custom_fields;
		$fieldDefinition = $fieldSet->getDefinitionSet()
			->filterGroup($group)
			->filterEditable($fieldSet, 'user');

		$customFields = $this->filter('custom_fields', 'array');
		$customFieldsShown = array_keys($fieldDefinition->getFieldDefinitions());

		if ($customFieldsShown)
		{
			$form->setup(function () use ($fieldSet, $customFields, $customFieldsShown)
			{
				$fieldSet->bulkSet($customFields, $customFieldsShown);
			});
		}

		if ($entitySave)
		{
			$form->validateEntity($userProfile)->saveEntity($userProfile);
		}
	}

	public function checkCsrfIfNeeded($action, ParameterBag $params)
	{
		if (strtolower($action) == 'upgradepurchase')
		{
			return;
		}

		parent::checkCsrfIfNeeded($action, $params);
	}

	public function assertViewingPermissions($action)
	{
	}

	public function assertTfaRequirement($action)
	{
	}

	public function assertNotSecurityLocked($action)
	{
		switch (strtolower($action))
		{
			case 'alertspopup':
			case 'dismissnotice':
			case 'requestpassword':
			case 'visitormenu':
				break;

			case 'security':
				if (\XF::visitor()->security_lock === 'reset')
				{
					// don't allow direct password changes if a reset is pending
					parent::assertNotSecurityLocked($action);
				}
				break;

			default:
				parent::assertNotSecurityLocked($action);
		}
	}

	public function assertPolicyAcceptance($action)
	{
		$action = strtolower($action);

		if (strpos($action, 'twostep') === 0)
		{
			return;
		}

		switch ($action)
		{
			case 'dismissnotice':
			case 'visitormenu':
				break;

			default:
				parent::assertPolicyAcceptance($action);
		}
	}

	public function assertBoardActive($action)
	{
		switch (strtolower($action))
		{
			case 'dismissnotice':
			case 'visitormenu':
				break;

			default:
				parent::assertBoardActive($action);
		}
	}

	public static function getActivityDetails(array $activities)
	{
		return \XF::phrase('managing_account_details');
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return Entity|ConnectedAccountProvider
	 * @throws Exception
	 */
	protected function assertProviderExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists(ConnectedAccountProvider::class, $id, $with, $phraseKey);
	}

	protected function assertPasskeyExists(string $id, $with = null, ?string $phraseKey = null)
	{
		return $this->assertRecordExists(Passkey::class, $id, $with, $phraseKey);
	}

	/**
	 * @param      $id
	 * @param null $with
	 * @param null $phraseKey
	 *
	 * @return UserAlert
	 * @throws Exception
	 */
	protected function assertViewableAlert($id, $with = null, $phraseKey = null)
	{
		$alert = $this->assertRecordExists(UserAlert::class, $id, $with, $phraseKey);

		if ($alert->alerted_user_id != \XF::visitor()->user_id)
		{
			throw $this->exception($this->notFound());
		}

		if (!$alert->canView())
		{
			throw $this->exception($this->noPermission());
		}

		return $alert;
	}

	protected function assertTfaEnabled()
	{
		if (!\XF::config('enableTfa'))
		{
			throw $this->exception($this->noPermission());
		}
	}

	/**
	 * @return ConnectedAccountRepository
	 */
	protected function getConnectedAccountRepo()
	{
		return $this->repository(ConnectedAccountRepository::class);
	}
}
