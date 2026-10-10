<?php

declare(strict_types=1);

namespace App\Agents;

use App\Tools\SendEmailTool;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Ollama\Ollama;
use RuntimeException;

class MessageRouterAgent extends Agent
{
    private const BASE_SYSTEM_PROMPT = <<<'PROMPT'
You are an email routing agent. For every incoming message, classify it using the
provided rules and call the send_routed_email tool exactly once. Your only task is
to choose the appropriate recipient email address (category), provide a concise
subject if the API did not provide one, and call the sending tool. Do NOT rewrite,
summarize, or alter the user's original message body. The tool sends the original
message body unchanged. Do not ask follow-up questions or reply with a routing
suggestion instead of calling the tool. Only report success after the tool
confirms that the email was sent.
PROMPT;

    protected function provider(): AIProviderInterface
    {
        return new Ollama(
            url: getenv('OLLAMA_HOST') ?: 'http://ollama:11434/api',
            model: getenv('OLLAMA_MODEL') ?: 'qwen2.5:3b',
            parameters: ['temperature' => 0],
        );
    }

    protected function instructions(): string
    {
        $instructions = self::BASE_SYSTEM_PROMPT;
            $rulesPath = dirname(__DIR__, 2) . '/config/rules.txt';
            if (is_file($rulesPath)) {
                $rules = file_get_contents($rulesPath);
                if ($rules === false) {
                    throw new RuntimeException('Unable to read agent rules file: ' . $rulesPath);
        }

            $instructions .= "\n\nAdditional categorization rules:\n" . $rules;
        }

        return $instructions;
    }

    public static function forSender(
        string $originalSenderEmail,
        string $originalMessage,
        ?string $originalSubject = null,
        bool $urgent = false,
    ): self {
        return self::make()
            ->setThreadId('email-router-' . bin2hex(random_bytes(16)))
            ->addTool(new SendEmailTool($originalSenderEmail, $originalMessage, $originalSubject, $urgent));
    }

    public function route(string $message, string $sender): string
    {
        $response = $this->chat(new UserMessage($message));
        
        $content = $response->getMessage()->getContent();

        return (string)($content ?? 'Email routed successfully.');
    }
}