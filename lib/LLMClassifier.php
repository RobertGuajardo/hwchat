<?php
/**
 * LLMClassifier — Sends chat conversations to an LLM for analytics classification.
 *
 * Supports OpenAI and Anthropic providers. Returns structured classification
 * data or null on failure. All errors are logged to STDERR.
 */

class LLMClassifier
{
    private string $provider;
    private string $apiKey;
    private string $model;

    const CLASSIFICATION_PROMPT = <<<'PROMPT'
You are analyzing a chatbot conversation from a real estate community website. Classify this conversation and return ONLY valid JSON with no additional text.

Conversation:
{CONVERSATION}

Return this exact JSON structure:
{
  "intent_level": "browsing" | "interested" | "ready_to_buy",
  "topics": ["pricing", "amenities", ...],
  "price_range_min": number or null,
  "price_range_max": number or null,
  "bedrooms_requested": number or null,
  "builders_mentioned": ["Builder Name", ...],
  "objections": ["price_too_high", "hoa_concerns", ...],
  "cross_referrals": ["tenant_id_1", ...],
  "sentiment": "positive" | "neutral" | "negative",
  "xo_tool_called": true | false,
  "summary": "1-2 sentence summary of the conversation"
}

Use only these topic tags: pricing, amenities, schools, builders, floorplans, lot_info, location, hoa, move_in, tours, financing, inventory, community_info

Use only these objection tags: price_too_high, hoa_concerns, distance, flood_zone, construction, limited_inventory, school_concerns
PROMPT;

    const VALID_INTENTS     = ['browsing', 'interested', 'ready_to_buy'];
    const VALID_SENTIMENTS  = ['positive', 'neutral', 'negative'];
    const VALID_TOPICS      = ['pricing', 'amenities', 'schools', 'builders', 'floorplans', 'lot_info', 'location', 'hoa', 'move_in', 'tours', 'financing', 'inventory', 'community_info'];
    const VALID_OBJECTIONS  = ['price_too_high', 'hoa_concerns', 'distance', 'flood_zone', 'construction', 'limited_inventory', 'school_concerns'];

    public function __construct(string $provider, string $apiKey, string $model)
    {
        $this->provider = $provider;
        $this->apiKey   = $apiKey;
        $this->model    = $model;
    }

    /**
     * Classify a conversation. Returns parsed classification array, or null on failure.
     */
    public function classify(array $messages, string $tenantId): ?array
    {
        // Format messages as "visitor: ..." / "assistant: ..."
        $lines = [];
        foreach ($messages as $m) {
            $role = $m['role'] === 'user' ? 'visitor' : 'assistant';
            $lines[] = "{$role}: {$m['content']}";
        }
        $conversation = implode("\n", $lines);

        $prompt = str_replace('{CONVERSATION}', $conversation, self::CLASSIFICATION_PROMPT);

        // Call the LLM
        $responseText = $this->provider === 'anthropic'
            ? $this->callAnthropic($prompt)
            : $this->callOpenAI($prompt);

        if ($responseText === null) {
            return null;
        }

        // Parse JSON from response (strip markdown fences if present)
        $json = $responseText;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $json, $m)) {
            $json = $m[1];
        }
        $json = trim($json);

        $parsed = json_decode($json, true);
        if (!is_array($parsed)) {
            fwrite(STDERR, "[LLMClassifier] JSON parse failed for tenant {$tenantId}. Raw: " . substr($responseText, 0, 500) . "\n");
            return null;
        }

        // Validate and normalize
        return $this->validate($parsed, $tenantId);
    }

    /**
     * Call OpenAI chat completions API.
     */
    private function callOpenAI(string $prompt): ?string
    {
        $payload = [
            'model'    => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature'  => 0.2,
            'max_tokens'   => 800,
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$this->apiKey}",
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            fwrite(STDERR, "[LLMClassifier] OpenAI cURL error: {$curlError}\n");
            return null;
        }
        if ($httpCode !== 200) {
            fwrite(STDERR, "[LLMClassifier] OpenAI HTTP {$httpCode}: " . substr($response, 0, 300) . "\n");
            return null;
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Call Anthropic messages API.
     */
    private function callAnthropic(string $prompt): ?string
    {
        $payload = [
            'model'      => $this->model,
            'max_tokens' => 800,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.2,
        ];

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "x-api-key: {$this->apiKey}",
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            fwrite(STDERR, "[LLMClassifier] Anthropic cURL error: {$curlError}\n");
            return null;
        }
        if ($httpCode !== 200) {
            fwrite(STDERR, "[LLMClassifier] Anthropic HTTP {$httpCode}: " . substr($response, 0, 300) . "\n");
            return null;
        }

        $data = json_decode($response, true);
        return $data['content'][0]['text'] ?? null;
    }

    /**
     * Validate the LLM response structure and enum values.
     * Returns the normalized array, or null if validation fails.
     */
    private function validate(array $data, string $tenantId): ?array
    {
        $errors = [];

        // Required fields
        if (!isset($data['intent_level']) || !in_array($data['intent_level'], self::VALID_INTENTS)) {
            $errors[] = 'invalid intent_level: ' . ($data['intent_level'] ?? 'missing');
        }
        if (!isset($data['sentiment']) || !in_array($data['sentiment'], self::VALID_SENTIMENTS)) {
            $errors[] = 'invalid sentiment: ' . ($data['sentiment'] ?? 'missing');
        }
        if (!isset($data['summary']) || !is_string($data['summary']) || trim($data['summary']) === '') {
            $errors[] = 'missing or empty summary';
        }

        if (!empty($errors)) {
            fwrite(STDERR, "[LLMClassifier] Validation failed for tenant {$tenantId}: " . implode('; ', $errors) . "\n");
            return null;
        }

        // Filter topics and objections to valid values only
        $topics = array_values(array_intersect((array)($data['topics'] ?? []), self::VALID_TOPICS));
        $objections = array_values(array_intersect((array)($data['objections'] ?? []), self::VALID_OBJECTIONS));

        return [
            'intent_level'       => $data['intent_level'],
            'topics'             => $topics,
            'price_range_min'    => is_numeric($data['price_range_min'] ?? null) ? (int)$data['price_range_min'] : null,
            'price_range_max'    => is_numeric($data['price_range_max'] ?? null) ? (int)$data['price_range_max'] : null,
            'bedrooms_requested' => is_numeric($data['bedrooms_requested'] ?? null) ? (int)$data['bedrooms_requested'] : null,
            'builders_mentioned' => array_values(array_filter((array)($data['builders_mentioned'] ?? []), 'is_string')),
            'objections'         => $objections,
            'cross_referrals'    => array_values(array_filter((array)($data['cross_referrals'] ?? []), 'is_string')),
            'sentiment'          => $data['sentiment'],
            'xo_tool_called'     => !empty($data['xo_tool_called']),
            'summary'            => trim($data['summary']),
        ];
    }
}
