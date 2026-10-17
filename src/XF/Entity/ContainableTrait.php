<?php

namespace XF\Entity;

trait ContainableTrait
{
	public function getContentContainerId(): int
	{
		$column = $this->getContentContainerIdColumn();
		return $this->{$column};
	}
}
