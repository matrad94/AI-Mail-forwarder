<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Agents\MessageRouterAgent;
use App\Agents\SecurityGuardAgent;
use JsonException;

class DispatchController
{
    public function handle(): never
    {
        header('Content-Type: application/json; charset=utf-8');

        $rawInput = file_get_contents('php://input');
        if ($rawInput === false) {
            $this->respond(400, ['error' => 'Unable to read request body.']);
        }

        try {
            $payload = json_decode($rawInput, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->respond(400, ['error' => 'Request body must contain valid JSON.']);
        }

        if (
            !is_array($payload)
            || !isset($payload['email'], $payload['message'])
            || !is_string($payload['email'])
            || !is_string($payload['message'])
            || trim($payload['email']) === ''
            || trim($payload['message']) === ''
        ) {
            $this->respond(400, ['error' => 'Fields "email" and "message" are required.']);
        }

        if (
            array_key_exists('subject', $payload)
            && $payload['subject'] !== null
            && !is_string($payload['subject'])
        ) {
            $this->respond(400, ['error' => 'Field "subject" must be a string or null.']);
        }

        $email = trim($payload['email']);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->respond(400, ['error' => 'Field "email" must be a valid email address.']);
        }

        $message = $payload['message'];
        $subject = $payload['subject'] ?? null;
        if (is_string($subject) && trim($subject) === '') {
            $subject = null;
        }

        if ($this->getEnvBool('ENABLE_SECURITY_GUARD', true)) {
            $guard = SecurityGuardAgent::make()
                ->setThreadId('security-guard-' . bin2hex(random_bytes(16)));
            if (!$guard->isMessageSafe($message)) {
                $this->respond(403, ['error' => 'Message rejected for security reasons.']);
            }
        }

        $urgent = false;
        if ($this->getEnvBool('ENABLE_URGENCY_CHECK', true)) {
            $textToScan = ($subject ?? '') . ' ' . $message;
            $urgent = preg_match(
                '/\b(?:awaria|krytyczne|pilne|down|broken|ASAP)\b|nie\s+działa\s+cały\s+system/iu',
                $textToScan,
            ) === 1;
        }

        $reply = MessageRouterAgent::forSender($email, $message, $subject, $urgent)->route($message);
        $this->respond(200, ['message' => $reply]);
    }

    private function getEnvBool(string $key, bool $default = true): bool
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function respond(int $statusCode, array $payload): never
    {
        http_response_code($statusCode);
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
