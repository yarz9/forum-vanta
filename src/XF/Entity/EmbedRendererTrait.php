<?php

namespace XF\Entity;

use XF\Mvc\Entity\Structure;

trait EmbedRendererTrait
{
	public function addEmbedRendererBbCodeOptions(array &$renderOptions, $context, $type): void
	{
		$renderOptions['embeds'] = $this->Embeds ?: [];
	}

	public function getEmbeds(): array
	{
		return $this->_getterCache['Embeds'] ?? [];
	}

	public function setEmbeds($unfurls): void
	{
		$this->_getterCache['Embeds'] = $unfurls;
	}

	public static function addEmbedRendererStructureElements(Structure $structure): void
	{
		$structure->getters['Embeds'] = true;
	}
}
