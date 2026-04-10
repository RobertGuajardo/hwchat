<?php
ini_set("display_errors",0);ini_set("log_errors",1);ini_set("error_log","/home/rober253/hwchat.robertguajardo.com/chat_errors.log");
/**
 * POST /api/chat.php
 *
 * Main chat endpoint. Receives a message from the widget, loads the
 * tenant's config (system prompt, LLM keys), sends to OpenAI or Claude,
 * and returns the response. Parses [ACTION:...] blocks from LLM output.
 *
 * Request:
 * {
 *   "tenant_id": "acme",
 *   "session_id": "uuid",
 *   "message": "What services do you offer?",
 *   "history": [{ "role": "user", "content": "..." }, ...],
 *   "page_url": "https://acme.com/services"
 * }
 *
 * Response:
 * {
 *   "reply": "We offer web design, development...",
 *   "action": null | "show_lead_form" | "show_calendar",
 *   "lead_data": { ... }  // optional, if action = show_lead_form
 * }
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/Embeddings.php';
require_once __DIR__ . '/../lib/CecilianXO.php';

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed.', 405);
}

// Parse input
$input = getJsonInput();
$tenantId  = trim($input['tenant_id'] ?? '');
$sessionId = trim($input['session_id'] ?? '');
$message   = trim($input['message'] ?? '');
$history   = $input['history'] ?? [];
$pageUrl   = $input['page_url'] ?? '';

// Validate
if (empty($tenantId))  jsonError('Missing tenant_id.');
if (empty($sessionId)) jsonError('Missing session_id.');
if (empty($message))   jsonError('Missing message.');

// CORS
handleCors($config, $tenantId);

// Load tenant
$tenant = Database::getTenant($tenantId);
if (!$tenant) {
    jsonError('Tenant not found.', 404);
}

// Rate limiting
$ipHash = getIpHash();
$allowed = Database::checkRateLimit(
    $tenantId,
    $ipHash,
    $tenant['rate_limit_per_minute'],
    $tenant['rate_limit_per_hour']
);
if (!$allowed) {
    jsonError('Rate limit exceeded. Please try again shortly.', 429);
}

// Get or create session
$session = Database::getOrCreateSession($sessionId, $tenantId, [
    'page_url'   => $pageUrl,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    'ip_hash'    => $ipHash,
]);

// Check conversation length
if ($session['message_count'] >= $tenant['max_conversation_length']) {
    jsonError('Conversation limit reached. Please start a new chat.', 429);
}

// Save user message
Database::saveMessage($sessionId, 'user', $message);

// Build messages array for LLM
$llmMessages = [];

// Add history (last 20 messages)
foreach (array_slice($history, -20) as $h) {
    if (!empty($h['role']) && !empty($h['content'])) {
        $llmMessages[] = ['role' => $h['role'], 'content' => $h['content']];
    }
}

// Add current message
$llmMessages[] = ['role' => 'user', 'content' => $message];


// Load master prompt and prepend to tenant prompt
$masterPrompt = Database::getMasterPrompt();

// System prompt
$systemPrompt = $tenant['system_prompt'] ?? '';
if ($masterPrompt) {
    $systemPrompt = $masterPrompt . "

" . $systemPrompt;
}

// Inject community contact info with pre-formatted links for LLM
$contactParts = [];
if (!empty($tenant['community_phone'])) $contactParts[] = 'Phone: ' . $tenant['community_phone'];
if (!empty($tenant['community_email'])) $contactParts[] = 'Email: ' . $tenant['community_email'];
if (!empty($tenant['community_address'])) {
    $addr = $tenant['community_address'];
    $contactParts[] = 'Address: ' . $addr;
    $contactParts[] = 'Directions link: [Get Directions](https://www.google.com/maps/dir/?api=1&destination=' . urlencode($addr) . ')';
}
$contactParts[] = 'Tour scheduling link: [Schedule a Tour](action:calendar)';
if ($contactParts) {
    $systemPrompt .= "\n\n=== COMMUNITY CONTACT INFO ===\n" . implode("\n", $contactParts);
}

// Inject hours of operation from availability rules
$hoursContext = getHoursContext(Database::db(), $tenantId);
if ($hoursContext) {
    $systemPrompt .= "\n\n=== HOURS OF OPERATION ===\n" . $hoursContext;
}

// Inject Knowledge Base context
if (!empty($tenant['kb_enabled'])) {
    $kbContext = getKbContext(Database::db(), $tenantId, $message, (int)($tenant['kb_max_context'] ?? 3));
    if ($kbContext) {
        $systemPrompt .= "\n\n=== BUSINESS KNOWLEDGE ===\nUse the following information to answer the visitor's questions accurately. If the answer is in this knowledge base, use it. If not, answer based on your general instructions.\n\n" . $kbContext;
    }
}

// Build XO tools if enabled for this tenant
$xoTools = [];
$xoClient = null;
if (!empty($tenant['xo_enabled']) && !empty($tenant['xo_api_base_url']) && !empty($tenant['xo_project_slug'])) {
    $xoClient = new CecilianXO($tenant['xo_api_base_url'], $tenant['xo_project_slug']);
    $xoTools = getXoToolDefinitions();
}

// Properties captured from XO tool calls for widget card rendering
$capturedProperties = [];

// Determine which LLM to use
$primaryLlm = $tenant['primary_llm'] ?? 'openai';
$reply = null;
$provider = null;
$tokensUsed = 0;

// Try primary LLM (with tool-use loop if XO enabled)
try {
    if ($primaryLlm === 'openai') {
        [$reply, $tokensUsed] = callOpenAIWithTools($tenant, $systemPrompt, $llmMessages, $config, $xoTools, $xoClient, $capturedProperties);
        $provider = 'openai';
    } else {
        [$reply, $tokensUsed] = callAnthropicWithTools($tenant, $systemPrompt, $llmMessages, $config, $xoTools, $xoClient, $capturedProperties);
        $provider = 'anthropic';
    }
} catch (Exception $e) {
    // Fallback to the other LLM
    try {
        if ($primaryLlm === 'openai') {
            [$reply, $tokensUsed] = callAnthropicWithTools($tenant, $systemPrompt, $llmMessages, $config, $xoTools, $xoClient, $capturedProperties);
            $provider = 'anthropic';
        } else {
            [$reply, $tokensUsed] = callOpenAIWithTools($tenant, $systemPrompt, $llmMessages, $config, $xoTools, $xoClient, $capturedProperties);
            $provider = 'openai';
        }
    } catch (Exception $e2) {
        jsonError('AI service temporarily unavailable. Please try again.', 503);
    }
}

// Parse action blocks from the reply
$action = null;
$leadData = null;
$cleanReply = $reply;

// [ACTION:LEAD_CAPTURE] { ... } [/ACTION]
if (preg_match('/\[ACTION:LEAD_CAPTURE\]\s*\{([^}]*)\}/s', $reply, $m)) {
    // Parse key-value pairs from the action block
    $leadData = [];
    // Try JSON parse first
    $jsonAttempt = json_decode('{' . $m[1] . '}', true);
    if (is_array($jsonAttempt) && !empty($jsonAttempt)) {
        $leadData = array_change_key_case($jsonAttempt, CASE_LOWER);
    } elseif (!empty(trim($m[1]))) {
        // Fallback to key:value parsing
        foreach (explode("\n", $m[1]) as $line) {
            $line = trim($line);
            if (preg_match('/^["\']?(\w+)["\']?\s*[:=]\s*["\']?(.+?)["\']?\s*,?\s*$/', $line, $kv)) {
                $leadData[strtolower($kv[1])] = trim($kv[2], "\"' ");
            }
        }
    }

    // If the AI captured name + (email or phone), auto-save the lead
    $hasName = !empty($leadData['name']);
    $hasContact = !empty($leadData['email']) || !empty($leadData['phone']);

    if ($hasName && $hasContact) {
        // Auto-save lead to database
        $autoLeadId = Database::saveLead($tenantId, [
            'session_id'   => $sessionId,
            'name'         => $leadData['name'] ?? '',
            'email'        => $leadData['email'] ?? '',
            'phone'        => $leadData['phone'] ?? '',
            'message'      => $leadData['message'] ?? null,
            'project_type' => $leadData['project_type'] ?? null,
            'budget'       => $leadData['budget'] ?? null,
        ]);

        // Send email notification
        if (!empty($tenant['lead_email'])) {
            $notifSent = @sendLeadNotificationFromChat($tenant, $leadData);
            if ($notifSent && $autoLeadId) {
                $db = Database::db();
                $stmt = $db->prepare('UPDATE leads SET email_sent = TRUE WHERE id = :id');
                $stmt->execute(['id' => $autoLeadId]);
            }
        }

        // Don't show the form — lead is already saved
        $action = null;
    } else {
        // No usable data — show the form so the visitor can fill it in
        $action = 'show_lead_form';
    }

    // Remove the action block from the visible reply
    $cleanReply = trim(preg_replace('/\[ACTION:LEAD_CAPTURE\]\s*\{[^}]*\}\s*/s', '', $reply));
}

// [ACTION:CHECK_AVAILABILITY]
if (strpos($reply, '[ACTION:CHECK_AVAILABILITY]') !== false) {
    $action = 'show_calendar';
    $cleanReply = trim(str_replace('[ACTION:CHECK_AVAILABILITY]', '', $cleanReply));
}

// [ACTION:BOOK_CALL] { ... } [/ACTION]
if (preg_match('/\[ACTION:BOOK_CALL\]\s*\{([^}]*)\}/s', $reply, $m)) {
    $action = 'show_calendar';
    $cleanReply = trim(preg_replace('/\[ACTION:BOOK_CALL\]\s*\{[^}]*\}\s*/s', '', $cleanReply));
}

// Clean up any remaining action tags the AI might have output
$cleanReply = trim(preg_replace('/\[\/?ACTION[^\]]*\]/i', '', $cleanReply));
// Also strip any orphaned [/ACTION] with surrounding whitespace
$cleanReply = trim(preg_replace('/\s*\[\/ACTION\]\s*/i', '', $cleanReply));

// Save assistant reply
Database::saveMessage($sessionId, 'assistant', $cleanReply, $tokensUsed, $provider);

// Build response
$response = ['reply' => $cleanReply, 'action' => $action];
if (!empty($capturedProperties)) {
    $response['properties'] = $capturedProperties;
}
if ($leadData) {
    $response['lead_data'] = $leadData;
}

jsonResponse($response);


// ===========================================================================
// LLM CALL FUNCTIONS (with tool-use support for XO inventory search)
// ===========================================================================

/**
 * XO property search tool definition (shared by OpenAI and Anthropic).
 */
function getXoToolDefinitions(): array
{
    return [[
        'name'        => 'search_available_homes',
        'description' => 'Search for available homes and homesites in this community. Use this whenever a visitor asks about available properties, pricing, specific builders, move-in ready homes, lot availability, or anything related to home inventory. Always use this tool -- never guess about availability or pricing. TYPE GUIDANCE: pass type=home when visitor says home/house/built home; pass type=homesite when visitor says lot/land/homesite; omit type only when visitor is clearly open to both. For broad queries like "what homes are available" or "what do you have", default to type=home and do NOT include homesites. BROAD QUERY GUIDANCE: When the visitor asks a general question without specifying a listing type (e.g. "what homes are available", "what do you have"), pass type=home, sort_by=balanced, and fetch_all=true but do NOT pass listing_type. The balanced sort prioritizes move-in ready homes first, then under construction, shuffled for builder fairness. At the end of your response, use the summary breakdown to mention what is available — for example: "Treeline currently has 22 move-in ready homes and 40 under construction. We also have buildable lots. Would you like to focus on a specific category, price range, or builder?" LISTING TYPE GUIDANCE: use listing_type to filter by readiness -- move_in_ready for quick move-in or ready homes, under_construction for homes being built, homesite for vacant buildable lots. This is preferred over the raw status param. LOT TYPE GUIDANCE: use lot_type to filter product type -- townhome for attached/townhome/zipper/rear-load products, duplex for duplex homes, oversized for acreage or large lots (80ft+), standard for typical single-family detached. RESPONSE FORMAT: Keep your text response concise. For each property, show: the address in bold as the heading (do NOT prefix with "Address:"), then embed the property photo using markdown image syntax like ![address](photo_url) -- never write Photo: before it. Then ALWAYS on its own line show the status using the listing_type field exactly like this -- "Status: Move In Ready" or "Status: Under Construction" or "Status: Home Site" -- this line is REQUIRED and must never be omitted. Then show price, builder, bedrooms, bathrooms, square footage, stories, and garage each as separate line items. If a property has a floor_plan_url, share it as [View Floor Plan](url). Do NOT combine multiple specs on one line -- keep each on its own line for readability. SORT GUIDANCE: Use sort_by=balanced for broad/general queries (e.g. "what homes are available") — it shows move-in ready first then under construction, shuffled for builder fairness. Use sort_by=random for filtered queries (e.g. when listing_type is specified). Use sort_by=price_asc ONLY when visitor specifically asks for cheapest. When visitor asks for cheapest or most expensive HOME, also pass type=home so homesites are excluded. Use sort_by=price_desc ONLY for most expensive. Use sort_by=sqft_desc for biggest. SUBSET MESSAGING: The tool result includes a "summary" field like "Showing 5 of 28 matching properties." You MUST include this exact information at the start of your response (e.g. "Here are 5 of 28 homes under construction at Treeline:"). Then ask if they would like to narrow by price range, builder, bedrooms, stories, or see more options. FLOOR PLANS: If a property has a floor_plan_url, ALWAYS include it as [View Floor Plan](url) -- never skip it. FETCH ALL: set fetch_all=true when visitor asks for cheapest, most expensive, or exact counts so all pages are searched.',
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'price_max' => [
                    'type'        => 'integer',
                    'description' => 'Maximum price the visitor is looking for',
                ],
                'beds_min' => [
                    'type'        => 'integer',
                    'description' => 'Minimum number of bedrooms (greater-than-or-equal)',
                ],
                'baths_min' => [
                    'type'        => 'number',
                    'description' => 'Minimum number of bathrooms (greater-than-or-equal)',
                ],
                'stories' => [
                    'type'        => 'integer',
                    'description' => 'Number of stories (exact match: 1 or 2)',
                ],
                'garage_min' => [
                    'type'        => 'integer',
                    'description' => 'Minimum garage spaces (greater-than-or-equal)',
                ],
                'sqft_min' => [
                    'type'        => 'integer',
                    'description' => 'Minimum square footage',
                ],
                'sqft_max' => [
                    'type'        => 'integer',
                    'description' => 'Maximum square footage',
                ],
                'builder' => [
                    'type'        => 'string',
                    'description' => 'Specific builder name if the visitor asked about one',
                ],
                'type' => [
                    'type'        => 'string',
                    'enum'        => ['home', 'homesite'],
                    'description' => "Pass home for built homes/houses. Pass homesite for vacant lots/land. Omit only when visitor wants both.",
                ],
                'listing_type' => [
                    'type'        => 'string',
                    'enum'        => ['move_in_ready', 'under_construction', 'homesite'],
                    'description' => 'Filter by readiness. move_in_ready = ready now or within 30 days. under_construction = still being built. homesite = vacant buildable lot.',
                ],
                'lot_type' => [
                    'type'        => 'string',
                    'enum'        => ['townhome', 'duplex', 'oversized', 'standard'],
                    'description' => 'Filter by product type. townhome = attached/zipper/rear-load. duplex = duplex homes. oversized = acreage or 80ft+ lots. standard = typical single-family detached.',
                ],
                'sort_by' => [
                    'type'        => 'string',
                    'enum'        => ['price_asc', 'price_desc', 'random', 'balanced', 'status_first', 'sqft_desc', 'sqft_asc'],
                    'description' => 'Sort order. Use balanced for broad/general queries — shows move-in ready first then under construction, shuffled for builder fairness. Use random for filtered queries (e.g. listing_type). Use price_asc ONLY when visitor asks for cheapest, price_desc for most expensive, sqft_desc for biggest.',
                ],
                'fetch_all' => [
                    'type'        => 'boolean',
                    'description' => 'Set true for cheapest, most expensive, or exact count queries to search all pages.',
                ],
            ],
        ],
    ]];
}

/**
 * Execute a tool call from the LLM.
 * Returns the tool result as a string (JSON).
 */
function executeToolCall(string $toolName, array $args, ?CecilianXO $xoClient, array &$capturedProperties = []): string
{
    if ($toolName === 'search_available_homes' && $xoClient) {
        $result = $xoClient->search($args, 5);
        if (!empty($result['properties'])) {
            $capturedProperties = $result['properties'];
        }
        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return json_encode(['error' => "Unknown tool: $toolName"]);
}

/**
 * Call OpenAI with optional tool-use support.
 * Handles the tool call → result → final response loop.
 * Returns [reply_text, tokens_used].
 */
function callOpenAIWithTools(array $tenant, string $systemPrompt, array $messages, array $config, array $tools = [], ?CecilianXO $xoClient = null, array &$capturedProperties = []): array
{
    $apiKey = $tenant['openai_api_key'] ?: ($config['default_openai_key'] ?? '');
    if (empty($apiKey)) {
        throw new Exception('No OpenAI API key configured.');
    }

    $model = $tenant['openai_model'] ?? 'gpt-4o';
    $maxTokens = $tenant['max_tokens'] ?? 500;
    $totalTokens = 0;

    $allMessages = array_merge(
        [['role' => 'system', 'content' => $systemPrompt]],
        $messages
    );

    // Allow up to 2 tool-use rounds (search → response, or search → refine → response)
    for ($round = 0; $round < 3; $round++) {
        $payload = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => $allMessages,
        ];

        // Add tools on first rounds only
        if (!empty($tools) && $round < 2) {
            $payload['tools'] = array_map(function ($t) {
                return [
                    'type'     => 'function',
                    'function' => [
                        'name'        => $t['name'],
                        'description' => $t['description'],
                        'parameters'  => $t['parameters'],
                    ],
                ];
            }, $tools);
        }

        $data = openaiRequest($apiKey, $payload);
        $totalTokens += $data['usage']['total_tokens'] ?? 0;

        $choice = $data['choices'][0] ?? [];
        $msg = $choice['message'] ?? [];
        $finishReason = $choice['finish_reason'] ?? 'stop';

        // If the model wants to use a tool
        if ($finishReason === 'tool_calls' && !empty($msg['tool_calls'])) {
            // Add assistant message with tool calls to conversation
            $allMessages[] = $msg;

            // Execute each tool call and add results
            foreach ($msg['tool_calls'] as $tc) {
                $fnName = $tc['function']['name'] ?? '';
                $fnArgs = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
                $result = executeToolCall($fnName, $fnArgs, $xoClient, $capturedProperties);

                $allMessages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $tc['id'],
                    'content'      => $result,
                ];
            }
            continue; // Next round to get final response
        }

        // Normal text response — we're done
        $reply = $msg['content'] ?? '';
        if (empty($reply)) {
            throw new Exception('Empty response from OpenAI.');
        }
        return [$reply, $totalTokens];
    }

    throw new Exception('OpenAI tool-use loop exceeded max rounds.');
}

/**
 * Low-level OpenAI API request.
 */
function openaiRequest(string $apiKey, array $payload): array
{
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "Authorization: Bearer $apiKey",
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("OpenAI API returned HTTP $httpCode");
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new Exception('Invalid response from OpenAI.');
    }

    return $data;
}

/**
 * Call Anthropic with optional tool-use support.
 * Handles the tool call → result → final response loop.
 * Returns [reply_text, tokens_used].
 */
function callAnthropicWithTools(array $tenant, string $systemPrompt, array $messages, array $config, array $tools = [], ?CecilianXO $xoClient = null, array &$capturedProperties = []): array
{
    $apiKey = $tenant['anthropic_api_key'] ?: ($config['default_anthropic_key'] ?? '');
    if (empty($apiKey)) {
        throw new Exception('No Anthropic API key configured.');
    }

    $model = $tenant['anthropic_model'] ?? 'claude-sonnet-4-20250514';
    $maxTokens = $tenant['max_tokens'] ?? 500;
    $totalTokens = 0;

    $allMessages = $messages;

    for ($round = 0; $round < 3; $round++) {
        $payload = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $systemPrompt,
            'messages'   => $allMessages,
        ];

        // Add tools on first rounds only
        if (!empty($tools) && $round < 2) {
            $payload['tools'] = array_map(function ($t) {
                return [
                    'name'         => $t['name'],
                    'description'  => $t['description'],
                    'input_schema' => $t['parameters'],
                ];
            }, $tools);
        }

        $data = anthropicRequest($apiKey, $payload);
        $totalTokens += ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0);

        $stopReason = $data['stop_reason'] ?? 'end_turn';
        $content = $data['content'] ?? [];

        // Check if the model wants to use a tool
        $hasToolUse = false;
        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'tool_use') {
                $hasToolUse = true;
                break;
            }
        }

        if ($hasToolUse && $stopReason === 'tool_use') {
            // Add assistant message with full content (text + tool_use blocks)
            $allMessages[] = ['role' => 'assistant', 'content' => $content];

            // Execute tool calls and build result message
            $toolResults = [];
            foreach ($content as $block) {
                if (($block['type'] ?? '') === 'tool_use') {
                    $result = executeToolCall($block['name'], $block['input'] ?? [], $xoClient, $capturedProperties);
                    $toolResults[] = [
                        'type'       => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'content'    => $result,
                    ];
                }
            }

            $allMessages[] = ['role' => 'user', 'content' => $toolResults];
            continue; // Next round
        }

        // Extract text from response
        $reply = '';
        foreach ($content as $block) {
            if (($block['type'] ?? '') === 'text') {
                $reply .= $block['text'];
            }
        }

        if (empty($reply)) {
            throw new Exception('Empty response from Anthropic.');
        }

        return [$reply, $totalTokens];
    }

    throw new Exception('Anthropic tool-use loop exceeded max rounds.');
}

/**
 * Low-level Anthropic API request.
 */
function anthropicRequest(string $apiKey, array $payload): array
{
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "x-api-key: $apiKey",
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Anthropic API returned HTTP $httpCode");
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new Exception('Invalid response from Anthropic.');
    }

    return $data;
}

// Legacy wrappers for backward compatibility (non-XO tenants)
function callOpenAI(array $tenant, string $systemPrompt, array $messages, array $config): array
{
    return callOpenAIWithTools($tenant, $systemPrompt, $messages, $config);
}

function callAnthropic(array $tenant, string $systemPrompt, array $messages, array $config): array
{
    return callAnthropicWithTools($tenant, $systemPrompt, $messages, $config);
}


// ===========================================================================
// ===========================================================================

require_once __DIR__ . '/../lib/Embeddings.php';

/**
 * Search KB entries using pgvector cosine similarity (semantic search).
 * Falls back to full-text search if embedding generation fails.
 */
function getKbContext(PDO $db, string $tenantId, string $message, int $maxChunks = 3): string
{
    global $config;

    $searchTerm = trim($message);
    if (strlen($searchTerm) < 3) {
        return '';
    }

    $entries = [];

    // PRIMARY: pgvector cosine similarity search
    $tenant = Database::getTenant($tenantId);
    $apiKey = Embeddings::getApiKey($tenant, $config);

    if (!empty($apiKey)) {
        $queryVector = Embeddings::generate($searchTerm, $apiKey);

        if ($queryVector !== null) {
            $vecLiteral = Embeddings::toSql($queryVector);
            $stmt = $db->prepare("
                SELECT title, content, source_type,
                       1 - (embedding <=> :vec::vector) AS similarity
                FROM kb_entries
                WHERE tenant_id = :tid AND is_active = TRUE AND embedding IS NOT NULL
                ORDER BY embedding <=> :vec2::vector
                LIMIT :max_chunks
            ");
            $stmt->bindValue('tid', $tenantId);
            $stmt->bindValue('vec', $vecLiteral);
            $stmt->bindValue('vec2', $vecLiteral);
            $stmt->bindValue('max_chunks', $maxChunks, PDO::PARAM_INT);
            $stmt->execute();
            $entries = $stmt->fetchAll();

            // Filter out low-similarity results (below 0.3 threshold)
            $threshold = (float)($tenant['kb_match_threshold'] ?? 0.3);
            $entries = array_filter($entries, fn($e) => ($e['similarity'] ?? 0) >= $threshold);
        }
    }

    // FALLBACK: full-text search if vector search returned nothing
    if (empty($entries)) {
        $entries = getKbContextFullText($db, $tenantId, $searchTerm, $maxChunks);
    }

    if (empty($entries)) {
        return '';
    }

    $context = '';
    foreach ($entries as $entry) {
        if ($entry['source_type'] === 'faq' && $entry['title']) {
            $context .= "Q: {$entry['title']}\nA: {$entry['content']}\n\n";
        } elseif ($entry['title']) {
            $context .= "--- {$entry['title']} ---\n{$entry['content']}\n\n";
        } else {
            $context .= "{$entry['content']}\n\n";
        }
    }

    return trim($context);
}

/**
 * Full-text search fallback (original keyword-based approach).
 */
function getKbContextFullText(PDO $db, string $tenantId, string $searchTerm, int $maxChunks): array
{
    $text = strtolower($searchTerm);
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    $stopWords = ['the','a','an','is','are','was','were','be','been','have','has','had',
        'do','does','did','will','would','shall','should','may','might','must','can','could',
        'i','you','he','she','it','we','they','me','him','her','us','them','my','your','his',
        'its','our','their','this','that','these','those','am','if','or','and','but','not',
        'no','so','as','at','by','for','from','in','into','of','on','to','with','about','what',
        'which','who','whom','when','where','why','how','all','each','every','both','few',
        'more','most','other','some','such','than','too','very','just','also'];

    $keywords = array_diff($words, $stopWords);
    $keywords = array_filter($keywords, fn($w) => strlen($w) >= 3);

    if (empty($keywords)) return [];

    // PostgreSQL ts_rank full-text search
    $stmt = $db->prepare("
        SELECT title, content, source_type,
               ts_rank(to_tsvector('english', COALESCE(title,'') || ' ' || content), plainto_tsquery('english', :search)) AS relevance
        FROM kb_entries
        WHERE tenant_id = :tid AND is_active = TRUE
          AND to_tsvector('english', COALESCE(title,'') || ' ' || content) @@ plainto_tsquery('english', :search2)
        ORDER BY relevance DESC
        LIMIT :max_chunks
    ");
    $stmt->bindValue('tid', $tenantId);
    $stmt->bindValue('search', $searchTerm);
    $stmt->bindValue('search2', $searchTerm);
    $stmt->bindValue('max_chunks', $maxChunks, PDO::PARAM_INT);
    $stmt->execute();
    $entries = $stmt->fetchAll();

    // Fallback: ILIKE search
    if (empty($entries)) {
        $likeClauses = [];
        $params = ['tid' => $tenantId];
        $i = 0;
        foreach (array_slice(array_values($keywords), 0, 5) as $kw) {
            $likeClauses[] = "(title ILIKE :kw{$i} OR content ILIKE :kw{$i}b)";
            $params["kw{$i}"] = "%{$kw}%";
            $params["kw{$i}b"] = "%{$kw}%";
            $i++;
        }

        $whereClause = implode(' OR ', $likeClauses);
        $stmt = $db->prepare("
            SELECT title, content, source_type
            FROM kb_entries
            WHERE tenant_id = :tid AND is_active = TRUE AND ({$whereClause})
            LIMIT {$maxChunks}
        ");
        $stmt->execute($params);
        $entries = $stmt->fetchAll();
    }

    return $entries;
}


// ===========================================================================
// HOURS OF OPERATION CONTEXT
// ===========================================================================

/**
 * Load availability rules and format them as readable hours for the AI.
 */
function getHoursContext(PDO $db, string $tenantId): string
{
    $stmt = $db->prepare('
        SELECT day_of_week, start_time, end_time
        FROM availability_rules
        WHERE tenant_id = :tid AND is_active = TRUE
        ORDER BY day_of_week, start_time
    ');
    $stmt->execute(['tid' => $tenantId]);
    $rules = $stmt->fetchAll();

    if (empty($rules)) {
        return '';
    }

    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $schedule = [];

    foreach ($rules as $rule) {
        $dow = (int)$rule['day_of_week'];
        $dayName = $dayNames[$dow] ?? "Day $dow";
        $start = date('g:i A', strtotime($rule['start_time']));
        $end = date('g:i A', strtotime($rule['end_time']));
        $schedule[$dow] = "$dayName: $start – $end";
    }

    // Mark closed days
    for ($i = 0; $i < 7; $i++) {
        if (!isset($schedule[$i])) {
            $schedule[$i] = $dayNames[$i] . ': Closed';
        }
    }

    ksort($schedule);

    return "These are the business hours. Use them when visitors ask about hours, availability, or when the office is open.\n" . implode("\n", $schedule);
}


// ===========================================================================
// LEAD NOTIFICATION FROM CHAT
// ===========================================================================

/**
 * Send email notification when a lead is auto-captured from chat conversation.
 */
function sendLeadNotificationFromChat(array $tenant, array $lead): bool
{
    $to = $tenant['lead_email'];
    $name = $lead['name'] ?? 'Unknown';

    $subject = "New Lead from RobChat — {$name}";

    $body = "New lead auto-captured from chat conversation:\n\n";
    $body .= "Name: {$name}\n";
    if (!empty($lead['email']))        $body .= "Email: {$lead['email']}\n";
    if (!empty($lead['phone']))        $body .= "Phone: {$lead['phone']}\n";
    if (!empty($lead['message']))      $body .= "Message: {$lead['message']}\n";
    if (!empty($lead['project_type'])) $body .= "Project Type: {$lead['project_type']}\n";
    if (!empty($lead['budget']))       $body .= "Budget: {$lead['budget']}\n";
    $body .= "\n---\nPowered by RobChat";

    $headers = "From: RobChat <noreply@robchat.io>\r\n";
    if (!empty($lead['email'])) {
        $headers .= "Reply-To: {$lead['email']}\r\n";
    }

    return @mail($to, $subject, $body, $headers);
}
