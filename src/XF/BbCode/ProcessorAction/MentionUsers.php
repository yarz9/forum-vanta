<?php

namespace XF\BbCode\ProcessorAction;

use XF\App;
use XF\BbCode\Processor;
use XF\Str\Formatter;

class MentionUsers implements FiltererInterface
{
	/**
	 * @var Formatter $formatter
	 */
	protected $formatter;

	protected $mentionedUsers = [];

	public function __construct(Formatter $formatter)
	{
		$this->formatter = $formatter;
	}

	public function addFiltererHooks(FiltererHooks $hooks)
	{
		$hooks->addFinalHook('filterInput');
	}

	public function filterInput($string, Processor $processor)
	{
		$mentions = $this->formatter->getMentionFormatter();

		$string = $mentions->getMentionsBbCode($string);
		$this->mentionedUsers = $mentions->getMentionedUsers();

		return $string;
	}

	public function getMentionedUsers()
	{
		return $this->mentionedUsers;
	}

	public static function factory(App $app)
	{
		return new static($app->stringFormatter());
	}
}
