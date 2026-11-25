<?php

declare(strict_types=1);

const OPENAI_WIDGET_DEFAULT_MODEL = 'gpt-6-luna';
const OPENAI_WIDGET_RESPONSE_URL = 'https://api.openai.com/v1/responses';

function openai_widget_key(): ?string
{
    $key = trim(app_env('OPENAI_API_KEY', '') ?? '');
    return preg_match('/\A[A-Za-z0-9_-]{16,512}\z/D', $key) === 1 ? $key : null;
}

function openai_widget_model(): string
{
    $model = trim(app_env('APP_OPENAI_MODEL', OPENAI_WIDGET_DEFAULT_MODEL) ?? '');
    return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,99}\z/D', $model) === 1 ? $model : OPENAI_WIDGET_DEFAULT_MODEL;
}

function openai_widget_request_payload(array $messages, string $model): array
{
    $payload = [
        'model' => $model,
        'input' => array_map(
            static fn(array $message): array => ['role' => $message['role'], 'content' => $message['content']],
            array_slice($messages, -13)
        ),
        'store' => false,
        'max_output_tokens' => 1000,
    ];
    if ($model === OPENAI_WIDGET_DEFAULT_MODEL) {
        $payload['reasoning'] = ['effort' => 'none'];
    }
    return $payload;
}

/** Fixed HTTPS endpoint, bounded response and no forwarding of the credential. */
function openai_widget_transport(string $key, array $payload): array
{
    $handle = curl_init(OPENAI_WIDGET_RESPONSE_URL);
    if ($handle === false) {
        return ['status' => 0, 'body' => ''];
    }

    $body = '';
    try {
        $configured = curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 131072) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        if (!$configured || curl_exec($handle) === false) {
            return ['status' => 0, 'body' => '', 'timeout' => curl_errno($handle) === CURLE_OPERATION_TIMEDOUT];
        }
        return ['status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body];
    } finally {
        curl_close($handle);
    }
}

function openai_widget_parse_response(string $body): ?array
{
    $data = json_decode($body, true, 24);
    if (!is_array($data) || !in_array($data['status'] ?? null, ['completed', 'incomplete'], true)
        || !is_array($data['output'] ?? null)) {
        return null;
    }
    $parts = [];
    foreach ($data['output'] as $item) {
        if (!is_array($item) || ($item['type'] ?? '') !== 'message' || ($item['role'] ?? '') !== 'assistant'
            || !is_array($item['content'] ?? null)) {
            continue;
        }
        foreach ($item['content'] as $part) {
            if (!is_array($part)) {
                continue;
            }
            $text = match ($part['type'] ?? null) {
                'output_text' => $part['text'] ?? null,
                'refusal' => $part['refusal'] ?? null,
                default => null,
            };
            if (is_string($text) && $text !== '') {
                $parts[] = $text;
            }
        }
    }
    $text = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', implode("\n\n", $parts)) ?? '');
    if ($text === '') {
        return null;
    }
    $truncated = strlen($text) > OPENAI_WIDGET_ANSWER_BYTES;
    if ($truncated) {
        $text = substr($text, 0, OPENAI_WIDGET_ANSWER_BYTES);
        while (!app_is_valid_utf8($text)) {
            $text = substr($text, 0, -1);
        }
    }
    return ['text' => $text, 'truncated' => $truncated || $data['status'] === 'incomplete'];
}
