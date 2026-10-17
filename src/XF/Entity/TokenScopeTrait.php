<?php

namespace XF\Entity;

use XF\Mvc\Entity\Structure;

use function is_int, is_string;

trait TokenScopeTrait
{
	public function hasScope($scope): bool
	{
		return !empty($this->scopes[$scope]);
	}

	protected function verifyScopes(&$scopes): bool
	{
		$keyedCache = [];

		foreach ($scopes AS $key => $value)
		{
			if ($value === true || $value === 1 || $value === "1")
			{
				$keyedCache[$key] = true;
			}
			else if (is_int($key) && is_string($value))
			{
				$keyedCache[$value] = true;
			}
		}

		$scopes = $keyedCache;

		return true;
	}

	public static function addTokenScopeStructureElements(Structure $structure): void
	{
		$structure->columns['scopes'] = ['type' => self::JSON_ARRAY, 'default' => []];
	}
}
