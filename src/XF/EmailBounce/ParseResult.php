<?php

namespace XF\EmailBounce;

class ParseResult
{
	public const TYPE_UNKNOWN = 'unknown';
	public const TYPE_BOUNCE = 'bounce';
	public const TYPE_DELAY = 'delay';
	public const TYPE_CHALLENGE = 'challenge';
	public const TYPE_AUTOREPLY = 'autoreply';

	public $deliveryStatusContent;
	public $textContent;
	public $originalContent;

	public $date;

	public $recipient;
	public $recipientTrusted = false;

	public $remoteStatus;
	public $remoteDiagnostics;

	public $messageType;
}
