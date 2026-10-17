<?php

namespace XF\ControllerPlugin;

use XF\Data\CodeLanguage;

use function is_array;

class CodeEditorPlugin extends AbstractPlugin
{
	public function actionModeLoader($language)
	{
		/** @var CodeLanguage $languageData */
		$languageData = $this->data(CodeLanguage::class);
		$languages = $languageData->getSupportedLanguages(true);

		if (isset($languages[$language]))
		{
			$modeConfig = $languages[$language];
		}
		else
		{
			$modeConfig = [];
		}

		$reply = $this->view('XF:CodeEditor\ModeLoader', 'public:code_editor_mode_loader', [
			'modeConfig' => $modeConfig,
		]);

		if (isset($modeConfig['modes']))
		{
			if (is_array($modeConfig['modes']))
			{
				$mode = reset($modeConfig['modes']);
			}
			else
			{
				$mode = $modeConfig['modes'];
			}
		}
		else
		{
			$mode = '';
		}

		$reply->setJsonParams([
			'mode' => $mode,
			'mime' => $modeConfig['mime'] ?? '',
			'config' => $modeConfig['config'] ?? [],
			'language' => $language,
		]);

		return $reply;
	}
}
