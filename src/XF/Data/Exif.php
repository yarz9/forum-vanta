<?php

namespace XF\Data;

class Exif
{
	/**
	 * @param array<int, string|int> $tags
	 *
	 * @return array<string, array<string, string|int>>
	 */
	public function getExifData(array $tags): array
	{
		$exif = [];

		foreach ($tags AS $tagId => $tagValue)
		{
			$tagName = $this->getExifTagName($tagId);
			if ($tagName === null)
			{
				continue;
			}

			$tagGroup = $this->getExifTagGroup($tagId);
			$exif[$tagGroup][$tagName] = $tagValue;
		}

		$exif['FILE']['SectionsFound'] = $exif
			? 'ANY_TAG, ' . implode(', ', array_keys($exif))
			: '';

		return $exif;
	}

	public function getExifTagName(int $tagId): ?string
	{
		if (!function_exists('exif_tagname'))
		{
			throw new \RuntimeException('The exif extension is not installed');
		}

		$tagName = exif_tagname($tagId);
		if ($tagName === false)
		{
			return null;
		}

		return $tagName;
	}

	public function getExifTagGroup(int $tagId): string
	{
		if ($tagId === 0x8769 || $tagId === 0x8825)
		{
			return 'IFD0';
		}

		if ($tagId >= 0x0100 && $tagId <= 0x0FFF)
		{
			return 'IFD0';
		}

		// grouping is not comprehensive
		return 'EXIF';
	}
}
