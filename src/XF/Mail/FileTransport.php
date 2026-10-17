<?php

namespace XF\Mail;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class FileTransport extends AbstractTransport
{
	private $_savePath;

	public function __construct(?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
	{
		parent::__construct($dispatcher, $logger);

		$this->_savePath = sys_get_temp_dir();
	}

	public function setSavePath($path)
	{
		$this->_savePath = $path;
	}

	public function getSavePath()
	{
		return $this->_savePath;
	}

	protected function doSend(SentMessage $message): void
	{
		$subjectHeader = $message->getOriginalMessage()->getHeaders()->get('Subject');
		$subject = $subjectHeader ? $subjectHeader->getBody() : '';
		$subject = preg_replace('#[^a-z0-9_ -]#', '', strtolower($subject));
		$subject = strtr($subject, ' ', '-');
		$subject = substr($subject, 0, 30);

		$filename = time() . '.' . substr(md5(uniqid(microtime(), true)), 0, 6) . '-' . $subject . '.eml';
		$outputFile = $this->_savePath . \XF::$DS . $filename;
		file_put_contents($outputFile, $message->toString());
	}

	public function __toString(): string
	{
		return 'file://';
	}
}
