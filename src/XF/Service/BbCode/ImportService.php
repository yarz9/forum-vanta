<?php

namespace XF\Service\BbCode;

use XF\Entity\BbCode;
use XF\Entity\Phrase;
use XF\Finder\BbCodeFinder;
use XF\Service\AbstractXmlImport;
use XF\Util\Xml;

class ImportService extends AbstractXmlImport
{
	public function import(\SimpleXMLElement $xml)
	{
		$xmlBbCodes = $xml->bb_code;

		$existing = $this->finder(BbCodeFinder::class)
			->where('bb_code_id', $this->getBbCodeIds($xmlBbCodes))
			->order('bb_code_id')
			->fetch();

		foreach ($xmlBbCodes AS $xmlBbCode)
		{
			$data = $this->getBbCodeDataFromXml($xmlBbCode);
			$phrases = $this->getBbCodePhrasesFromXml($xmlBbCode);

			$bbCodeId = $data['bb_code_id'];

			if (isset($existing[$bbCodeId]))
			{
				/** @var BbCode $bbCode */
				$bbCode = $this->em()->find(BbCode::class, $bbCodeId);
			}
			else
			{
				$bbCode = $this->em()->create(BbCode::class);
			}
			$bbCode->bulkSet($data);
			$bbCode->save(false);

			foreach ($phrases AS $type => $text)
			{
				/** @var Phrase $masterPhrase */
				$masterPhrase = $bbCode->getMasterPhrase($type);
				$masterPhrase->phrase_text = $text;
				$masterPhrase->save();
			}
		}
	}

	protected function getBbCodeIds(\SimpleXMLElement $xmlBbCodes)
	{
		$bbCodeIds = [];
		foreach ($xmlBbCodes AS $xmlBbCode)
		{
			$bbCodeIds[] = (string) $xmlBbCode['bb_code_id'];
		}
		return $bbCodeIds;
	}

	protected function getBbCodeDataFromXml(\SimpleXMLElement $xmlBbCode)
	{
		$bbCodeData = [];

		foreach ($this->getAttributes() AS $attr)
		{
			$bbCodeData[$attr] = (string) $xmlBbCode[$attr];
		}

		$bbCodeData['replace_html'] = Xml::processSimpleXmlCdata($xmlBbCode->replace_html);
		$bbCodeData['replace_html_email'] = Xml::processSimpleXmlCdata($xmlBbCode->replace_html_email);
		$bbCodeData['replace_text'] = Xml::processSimpleXmlCdata($xmlBbCode->replace_text);

		$bbCodeData['active'] = 1;
		$bbCodeData['addon_id'] = '';

		return $bbCodeData;
	}

	protected function getBbCodePhrasesFromXml(\SimpleXMLElement $xmlBbCode)
	{
		return [
			'title' => (string) $xmlBbCode['title'],
			'desc' => Xml::processSimpleXmlCdata($xmlBbCode->desc),
			'example' => Xml::processSimpleXmlCdata($xmlBbCode->example),
			'output' => Xml::processSimpleXmlCdata($xmlBbCode->output),
		];
	}

	protected function getAttributes()
	{
		return [
			'bb_code_id', 'bb_code_mode', 'has_option',
			'callback_class', 'callback_method', 'option_regex',
			'trim_lines_after', 'plain_children', 'disable_smilies',
			'disable_nl2br', 'disable_autolink', 'allow_empty',
			'allow_signature', 'editor_icon_type', 'editor_icon_value',
		];
	}
}
