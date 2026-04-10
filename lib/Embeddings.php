<?php
/**
 * OpenAI Embeddings — generates and stores vector embeddings for KB entries.
 *
 * Uses text-embedding-3-small (1536 dimensions) via the OpenAI API.
 */

class Embeddings
{
    private const MODEL = 'text-embedding-3-small';
    private const API_URL = 'https://api.openai.com/v1/embeddings';

    /**
     * Generate an embedding vector for a text string.
     * Returns a float array of 1536 dimensions, or null on failure.
     */
    public static function generate(string $text, string $apiKey): ?array
    {
        if (empty($text) || empty($apiKey)) {
            return null;
        }

        // Truncate to ~8000 tokens (~32k chars) to stay within model limits
        $text = mb_substr($text, 0, 32000);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer $apiKey",
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => self::MODEL,
                'input' => $text,
            ]),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Embeddings API error HTTP $httpCode: $response");
            return null;
        }

        $data = json_decode($response, true);
        return $data['data'][0]['embedding'] ?? null;
    }

    /**
     * Build the text to embed for a KB entry.
     * Combines title + content for richer semantic representation.
     */
    public static function buildText(array $entry): string
    {
        $parts = [];
        if (!empty($entry['title'])) {
            $parts[] = $entry['title'];
        }
        $parts[] = $entry['content'];
        return implode("\n\n", $parts);
    }

    /**
     * Format a vector array as a pgvector literal string: '[0.1,0.2,...]'
     */
    public static function toSql(array $vector): string
    {
        return '[' . implode(',', $vector) . ']';
    }

    /**
     * Generate and store an embedding for a single KB entry.
     * Returns true on success.
     */
    public static function embedEntry(PDO $db, array $entry, string $apiKey): bool
    {
        $text = self::buildText($entry);
        $vector = self::generate($text, $apiKey);

        if ($vector === null) {
            return false;
        }

        $stmt = $db->prepare('UPDATE kb_entries SET embedding = :vec WHERE id = :id');
        $stmt->execute([
            'vec' => self::toSql($vector),
            'id'  => $entry['id'],
        ]);

        return true;
    }

    /**
     * Get the OpenAI API key — tenant-specific or global default.
     */
    public static function getApiKey(array $tenant, array $config): string
    {
        return $tenant['openai_api_key'] ?: ($config['default_openai_key'] ?? '');
    }
}
