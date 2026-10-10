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
provided rules and call the send_routed_email tool exactly once. Your tasks are:
1. Choose the appropriate recipient email address based on the rules.
2. If an explicit subject was NOT provided by the user, generate a concise, professional
   subject line in the SAME LANGUAGE as the user's incoming message.
3. Call the sending tool.

RULES & CONSTRAINTS:
- Do NOT rewrite, summarize, or alter the user's original message body.
- Do NOT ask follow-up questions or offer routing suggestions without executing the tool.
- Respond with a brief confirmation message in the SAME LANGUAGE as the incoming email (e.g. Polish for Polish emails, English for English emails).
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

    public function route(string $message): string
    {
        $response = $this->chat(new UserMessage($message));
        
        $content = trim((string)$response->getMessage()->getContent());

        if ($content !== '') {
            return $content;
        }

        return 'Wiadomość została pomyślnie przekazana. / Email routed successfully.';
    }
}