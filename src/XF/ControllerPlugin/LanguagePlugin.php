<?php

namespace XF\ControllerPlugin;

use XF\Entity\Language;
use XF\Repository\LanguageRepository;

use function intval;

class LanguagePlugin extends AbstractPlugin
{
	public function getActiveLanguageId()
	{
		$languageId = $this->request->getCookie('edit_language_id', null);
		if ($languageId === null)
		{
			$languageId = \XF::$developmentMode ? 0 : $this->options()->defaultLanguageId;
		}
		$languageId = intval($languageId);

		if ($languageId == 0 && !\XF::$developmentMode)
		{
			$languageId = $this->options()->defaultLanguageId;
		}

		return $languageId;
	}

	/**
	 * Gets the active editable language.
	 *
	 * @return Language
	 */
	public function getActiveEditLanguage()
	{
		$languageId = $this->getActiveLanguageId();

		if ($languageId == 0)
		{
			/** @var LanguageRepository $languageRepo */
			$languageRepo = $this->repository(LanguageRepository::class);
			$language = $languageRepo->getMasterLanguage();
		}
		else
		{
			$language = $this->em()->find(Language::class, $languageId);
		}

		/** @var $language \XF\Entity\Language */
		if (!$language || !$language->canEdit())
		{
			$language = $this->em()->find(Language::class, $this->options()->defaultLanguageId);
		}

		return $language;
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return Language
	 */
	public function assertLanguageExists($id, $with = null, $phraseKey = null)
	{
		if ($id === 0 || $id === "0")
		{
			/** @var LanguageRepository $languageRepo */
			$languageRepo = $this->repository(LanguageRepository::class);
			return $languageRepo->getMasterLanguage();
		}

		return $this->controller->assertRecordExists(Language::class, $id, $with, $phraseKey);
	}
}
