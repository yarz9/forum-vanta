<?php

namespace XF\Entity;

trait AssetVariantTrait
{
	abstract public function getAssetVariantSizeMap(): array;

	public function getAssetDisplayValue(string $fieldName, ?string $variant = null): string
	{
		$fieldValue = $this->$fieldName ?? null;

		if (!$fieldValue)
		{
			return '';
		}

		if ($variant === null)
		{
			return $fieldValue;
		}

		if (!isset($this->getAssetVariantSizeMap()[$fieldName][$variant]))
		{
			return $fieldValue;
		}

		$parts = pathinfo($fieldValue);
		return "$parts[dirname]/$parts[filename] - $variant.$parts[extension]";
	}
}
