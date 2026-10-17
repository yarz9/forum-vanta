<?php

namespace XF\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Entity\Style;
use XF\PrintableException;

trait StyleArchiveTrait
{
	protected function getStyleByDesignerModeInput(
		InputInterface $input,
		OutputInterface $output
	): Style
	{
		$designerMode = $input->getArgument('designer-mode');
		$style = \XF::em()->findOne(Style::class, ['designer_mode' => $designerMode]);

		if (!$style)
		{
			throw new PrintableException("No style with designer mode ID '$designerMode' could be found.");
		}

		return $style;
	}

	protected function getStyleByStyleIdInput(
		InputInterface $input,
		OutputInterface $output
	): Style
	{
		$styleId = $input->getArgument('style-id');
		$style = \XF::em()->find(Style::class, $styleId);

		if (!$style)
		{
			throw new PrintableException("No style with ID '$styleId' could be found.");
		}

		return $style;
	}
}
