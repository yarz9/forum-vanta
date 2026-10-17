<?php

namespace XF\Entity;

trait DatableTrait
{
	public function getContentDate(): int
	{
		$column = $this->getContentDateColumn();
		return $this->{$column};
	}
}
