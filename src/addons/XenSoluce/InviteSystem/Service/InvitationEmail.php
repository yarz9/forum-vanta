<?php

namespace XenSoluce\InviteSystem\Service;

use XenSoluce\ChipSystem\Entity\ChipItem;
use XenSoluce\InviteSystem\Entity\CodeInvitation;

class InvitationEmail extends \XF\Service\AbstractService
{
    /**
     * @var bool
     */
    protected bool $isAdmin;
    protected $email = [];
    protected array $replaces = [
        'username',
        'code'
    ];
    protected $subject = '';
    protected $verifySubject = false;
    protected string $token = '';
    protected $tokenId = 0;
    protected $type = 5;
    protected $invitation = null;
    protected bool $verifyEmail = false;
    protected $validationErrors;

    /**
     * Approve constructor.
     * @param \XF\App $app
     * @param bool $isAdmin
     */
	public function __construct(\XF\App $app, $isAdmin = false)
	{
		parent::__construct($app);
        $this->isAdmin = $isAdmin;
	}

    /**
     * @param array $email
     */
    public function setEmail($email)
    {
        $this->email = $this->clearEmail($email);
    }

    protected function clearEmail($email)
    {
        if(is_array($email)) {
            return array_filter($email, function ($currentEmail) {
                return $currentEmail !== '';
            });
        }

        return $email;
    }

    /**
     * @param $token
     * @param $tokenId
     */
    public function setToken($token = '', $tokenId = 0)
    {
        $this->token = $token;
        $this->tokenId = $tokenId;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @param bool $verifyEmail
     */
    public function setVerifyEmail(bool $verifyEmail): void
    {
        $this->verifyEmail = $verifyEmail;
    }

    /**
     * @param $subject
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    /**
     * @param bool $verifySubject
     */
    public function setVerifySubject(bool $verifySubject): void
    {
        $this->verifySubject = $verifySubject;
    }

    /**
     * @return null
     */
    public function getInvitation()
    {
        return $this->invitation;
    }

    /**
     * @param $errors
     * @return bool
     */
    public function validate(&$errors = []): bool
    {
        $this->validationErrors = $this->_validate();
        $errors = $this->validationErrors;
        return count($errors) == 0;
    }

    public function checkEmail()
    {
        $filterEmail = filter_var(
            is_array($this->email) ? $this->email : [$this->email],
            FILTER_VALIDATE_EMAIL,
            FILTER_REQUIRE_ARRAY
        );

        return !in_array(false, $filterEmail);
    }

    /**
     * @return array
     */
    public function _validate(): array
    {
        $errors = [];

        if($this->subject === '' && $this->verifySubject) {
            $errors[] = \XF::phrase('please_enter_value_for_required_field_x', [
                'field' => \XF::phrase('subject')
            ]);
        }

        if(!$this->checkEmail()) {
            $errors[] = \XF::phrase('please_enter_valid_email');
        }

        if(!empty($this->email) && $this->verifyEmail)
        {
            if(is_array($this->email))
            {
                foreach ($this->email as $email)
                {
                    if($this->verifyEmail($email) && $email !== '') {
                        $errors[] = \XF::phrase('xs_is_x_is_already_invited_or_registered', [
                            'email' => $email
                        ]);
                    }

                    if(count($this->email) === 1 && $email === "") {
                        $errors[] = \XF::phrase('xs_is_you_had_to_provide_at_least_one_email_address', [
                            'email' => $email
                        ]);
                    }
                }
            }
            else
            {
                if($this->verifyEmail($this->email)) {
                    $errors[] = \XF::phrase('xs_is_x_is_already_invited_or_registered', [
                        'email' => $this->email
                    ]);
                }
            }
        }

        return $errors;
    }

    /**
     * @param string $email
     * @return bool
     */
    protected function verifyEmail(string $email): bool
    {
        $emailSend = $this->finder('XenSoluce\InviteSystem:InvitationEmail')->where('email', $email);

        if(!$emailSend->total()) {
            $userEmailSend = $this->finder('XF:User')->where('email', $email);

            return (bool)$userEmailSend->total();
        }

        return true;
    }

    /**
     * @throws \XF\PrintableException
     */
    public function sendEmail()
    {
        $visitor = \XF::visitor();
        if(!empty($this->email))
        {
            if(is_array($this->email))
            {
                $i = 0;
                foreach ($this->email as $email)
                {
                    if($i > 9)
                    {
                        return;
                    }
                    if(filter_var($email, FILTER_VALIDATE_EMAIL))
                    {
                        $invitation = $this->invitation($visitor);
                        $this->sender($invitation, $email);
                        ++$i;
                    }
                }
            }
            else
            {
                $email = $this->email;
                $invitation = $this->invitation($visitor);
                $this->invitation = $invitation;
                $this->sender($invitation, $email);
            }
        }
    }

    /**
     * @param $visitor
     * @return CodeInvitation
     * @throws \XF\PrintableException
     */
    protected function invitation($visitor)
    {
        /** @var CodeInvitation $invitation */
        $invitation = $this->em()->create('XenSoluce\InviteSystem:CodeInvitation');
        $invitation->user_id = $visitor->user_id;
        $invitation->token_id = $this->tokenId;
        $invitation->token = $this->token;
        $invitation->type_code = $this->type;
        $invitation->save();
        return $invitation;
    }

    /**
     * @param array $values
     * @return string|string[]|null
     */
    protected function renderMessage(array $values = [])
    {
        $options = \XF::options();
        $message = $options->xs_is_predefined_message_email ;
        foreach ($this->replaces as $replace)
        {
            if(isset($values[$replace]))
            {
                $value = $values[$replace];
                $message = preg_replace('/\{' . $replace . '\}/', $value, $message);
            }
            else
            {
                $message = preg_replace('/\{' . $replace . '\}/', '', $message);
            }

        }

        return $message;
    }

    /**
     * @param $invitation
     * @param $email
     * @throws \XF\PrintableException
     */
    public function sender(CodeInvitation $invitation, $email)
    {
        $options = \XF::options();
        $visitor = \XF::visitor();
        $message = $this->renderMessage([
            'username' => $visitor->username,
            'code' => $invitation->code
        ]);
        $name = $options->xs_is_sender_name ?: $visitor->username;
        \XF::app()->mailer()->newMail()
            ->setTo($email)
            ->setFrom($options->xs_is_sender_email_address ?: $options->defaultEmailAddress, $name)
            ->setTemplate('xs_is_invitation_email', [
                'subject'    => $this->subject,
                'message'    => $message,
                'invitation' => $invitation
            ])
            ->send();

        /** @var \XenSoluce\InviteSystem\Entity\InvitationEmail $createEmail */
        $createEmail = $this->em()->create('XenSoluce\InviteSystem:InvitationEmail');
        $createEmail->user_id = $visitor->user_id;
        $createEmail->code = $invitation->code;
        $createEmail->code_id = $invitation->code_id;
        $createEmail->subject = $this->subject;
        $createEmail->message = $message;
        $createEmail->email = $email;
        $createEmail->is_admin = $this->isAdmin;
        $createEmail->save();
    }
}