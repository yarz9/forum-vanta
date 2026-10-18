<?php

namespace XF\Install\Data;

abstract class AbstractMySql
{
	abstract public function getTables(): array;

	abstract public function getData(): array;
}
