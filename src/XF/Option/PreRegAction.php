<?php

namespace XF\Option;

use XF\Entity\Option;
use XF\Repository\PermissionCombinationRepository;
use XF\Repository\UserGroupRepository;

use function array_key_exists, is_array;

class PreRegAction extends AbstractOption
{
	public static function renderOption(Option $option, array $htmlParams)
	{
		/** @var UserGroupRepository $userGroupRepo */
		$userGroupRepo = \XF::repository(UserGroupRepository::class);

		$userGroups = $userGroupRepo->getUserGroupOptionsData(false, 'option');

		return static::getTemplate('admin:option_template_preRegAction', $option, $htmlParams, [
			'userGroups' => $userGroups,
		]);
	}

	public static function verifyOption(array &$value, Option $option)
	{
		if (!array_key_exists('enabled', $value))
		{
			return true;
		}

		if (!array_key_exists('userGroups', $value) || !is_array($value['userGroups']))
		{
			$option->error(\XF::phrase('you_must_select_at_least_one_group_check_permissions_against'), $option->option_id);
			return false;
		}

		sort($value['userGroups'], SORT_NUMERIC);

		/** @var PermissionCombinationRepository $permComboRepo */
		$permComboRepo = \XF::app()->repository(PermissionCombinationRepository::class);
		$combination = $permComboRepo->getPermissionCombinationOrPlaceholder($value['userGroups']);
		if (!$combination->exists())
		{
			$combination->save();
		}

		$value['permissionCombinationId'] = $combination->permission_combination_id;

		return true;
	}
}
