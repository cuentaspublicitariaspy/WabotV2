<?php

require_once __DIR__ . '/config.php';

class OpenAI
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private float $temperature;

    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null
    ) {
        $this->apiKey = $apiKey ?? OPENAI_API_KEY;
        $this->model = $model ?? OPENAI_MODEL;
        $this->maxTokens = $maxTokens ?? OPENAI_MAX_TOKENS;
        $this->temperature = $temperature ?? OPENAI_TEMPERATURE;
    }

    public function chat(array $messages): ?string
    {
        $url = 'https://api.openai.com/v1/chat/completions';

        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("OpenAI curl error: $error");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("OpenAI HTTP error: $httpCode - $response");
            return null;
        }

        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? null;
    }
}
