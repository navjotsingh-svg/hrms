<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiCompletionService
{
    public function enabled(): bool
    {
        return filled(config('hrms.assistant.openai_api_key'))
            && (bool) config('hrms.assistant.use_ai', true);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, model: string}
     */
    public function chat(array $messages, ?int $maxTokens = null, float $temperature = 0.2): array
    {
        if (! $this->enabled()) {
            throw new \RuntimeException('AI is not configured.');
        }

        $response = Http::withToken((string) config('hrms.assistant.openai_api_key'))
            ->timeout(45)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('hrms.assistant.model', 'gpt-4o-mini'),
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens ?? (int) config('hrms.assistant.max_tokens', 700),
            ])
            ->throw()
            ->json();

        $content = trim((string) ($response['choices'][0]['message']['content'] ?? ''));

        if ($content === '') {
            throw new \RuntimeException('Empty response from AI provider.');
        }

        return [
            'content' => $content,
            'model' => (string) ($response['model'] ?? config('hrms.assistant.model', 'gpt-4o-mini')),
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chatJson(array $messages, ?int $maxTokens = null): array
    {
        $result = $this->chat($messages, $maxTokens, 0.1);
        $decoded = json_decode($result['content'], true);

        if (! is_array($decoded)) {
            $clean = preg_replace('/^```json\s*|\s*```$/', '', trim($result['content'])) ?? $result['content'];
            $decoded = json_decode($clean, true);
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('AI did not return valid JSON.');
        }

        return $decoded;
    }
}
