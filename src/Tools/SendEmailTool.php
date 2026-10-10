<?php

declare(strict_types=1);

namespace App\Tools;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolOutput;
use NeuronAI\Tools\ToolProperty;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

class SendEmailTool extends Tool
{
    protected string $name = 'send_routed_email';

    protected ?string $description = 'Send the message to its appropriate email department.';

    private const ALLOWED_RECIPIENTS = [
        'human-resources@example.com',
        'help-desk@example.com',
        'it@example.com',
        'kadry@example.com',
        'other@example.com',
    ];

    public function __construct(
        private readonly string $originalSenderEmail,
        private readonly string $originalMessage,
        private readonly ?string $originalSubject = null,
        private readonly bool $urgent = false,
    ) {
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'recipient_email',
                type: PropertyType::STRING,
                description: 'The selected department email address.',
                required: true,
                enum: self::ALLOWED_RECIPIENTS,
            ),
            new ToolProperty(
                name: 'subject',
                type: PropertyType::STRING,
                description: 'A concise subject based on the original message; ignored when the API provided a subject.',
                required: true,
            ),
        ];
    }

    public function __invoke(string $recipient_email, string $subject): ToolOutput
    {
        $recipientEmail = strtolower(trim($recipient_email));
        if (!in_array($recipientEmail, self::ALLOWED_RECIPIENTS, true)) {
            $recipientEmail = 'other@example.com';
        }

        $subject = $this->originalSubject ?? trim($subject);
        if ($subject === '') {
            $subject = 'Forwarded email';
        }
        if ($this->urgent && preg_match('/^\[URGENT\]\s*/i', $subject) !== 1) {
            $subject = '[URGENT] ' . $subject;
        }

        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = getenv('SMTP_HOST') ?: 'mailhog';
        $mailer->Port = (int)(getenv('SMTP_PORT') ?: 1025);
        $mailer->SMTPAuth = false;
        $mailer->SMTPSecure = '';
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->setFrom('no-reply@example.com', 'Email Router');
        $mailer->addAddress($recipientEmail);
        $mailer->addReplyTo($this->originalSenderEmail);
        $mailer->Subject = $subject;
        $mailer->Body = $this->originalMessage;
        $mailer->isHTML(false);

        try {
            if (!$mailer->send()) {
                throw new RuntimeException('Unable to send routed email: ' . $mailer->ErrorInfo);
            }
        } catch (PHPMailerException $exception) {
            throw new RuntimeException('Unable to send routed email: ' . $exception->getMessage(), 0, $exception);
        }

        return ToolOutput::text('Email sent to ' . $recipientEmail . '.');
    }
}
