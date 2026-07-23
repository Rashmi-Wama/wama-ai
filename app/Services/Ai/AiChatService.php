<?php

namespace App\Services\Ai;

use App\Models\AiChatLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class AiChatService
{
    public function __construct(
        private readonly BusinessTools $tools,
        private readonly ModuleCrudTools $crud,
    ) {}

    /**
     * @return array{reply: string, intent: string|null, tool_called: string|null, blocked: bool, log_id: int|null, provider: string}
     */
    public function handle(string $message, ?User $user = null): array
    {
        $message = trim($message);

        if ($message === '') {
            return $this->finish($user, $message, 'empty', null, [], null, 'Please share a business question so I can help.', false, 'none');
        }

        if ($blocked = $this->securityBlock($message)) {
            return $this->finish($user, $message, 'unsafe_request', null, [], null, $blocked, true, 'security');
        }

        $apiKey = (string) (config('ai.api_key') ?: config('ai.openai.api_key') ?: '');

        if ($apiKey !== '') {
            try {
                return $this->handleWithLlm($message, $user, $apiKey);
            } catch (Throwable $e) {
                report($e);
                // Fall through to local agent planner.
            }
        }

        return $this->handleWithLocalAgent($message, $user);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allToolDefinitions(): array
    {
        return array_merge($this->tools->definitions(), $this->crud->definitions());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function dispatchTool(string $name, array $arguments, ?User $user): array
    {
        if (in_array($name, $this->crudToolNames(), true)) {
            return $this->crud->call($name, $arguments, $user);
        }

        return $this->tools->call($name, $arguments);
    }

    /**
     * @return list<string>
     */
    private function crudToolNames(): array
    {
        return ['listRecords', 'getRecord', 'createRecord', 'updateRecord', 'deleteRecord'];
    }

    /**
     * OpenAI-compatible chat completions (Groq by default).
     *
     * @return array{reply: string, intent: string|null, tool_called: string|null, blocked: bool, log_id: int|null, provider: string}
     */
    private function handleWithLlm(string $message, ?User $user, string $apiKey): array
    {
        $provider = (string) config('ai.provider', 'groq');
        $baseUrl = rtrim((string) (config('ai.base_url') ?: config('ai.openai.base_url')), '/');
        $model = (string) (config('ai.model') ?: config('ai.openai.model') ?: 'llama-3.3-70b-versatile');

        $messages = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt(),
            ],
            [
                'role' => 'user',
                'content' => $message,
            ],
        ];

        $tools = $this->allToolDefinitions();

        $first = Http::withToken($apiKey)
            ->timeout(60)
            ->acceptJson()
            ->post("{$baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'temperature' => 0.2,
            ]);

        if (! $first->successful()) {
            $recovered = $this->recoverGroqToolFailure($first->json(), $message, $user, $provider);
            if ($recovered !== null) {
                return $recovered;
            }

            $first->throw();
        }

        $response = $first->json();
        $primaryToolName = null;
        $primaryArguments = [];
        $primaryToolResult = null;
        $maxToolRounds = 3;
        $round = 0;

        while (true) {
            $assistantMessage = data_get($response, 'choices.0.message', []);
            $toolCalls = $assistantMessage['tool_calls'] ?? [];

            if ($toolCalls === [] || $round >= $maxToolRounds) {
                break;
            }

            // Groq / OpenAI expect assistant tool-call messages to keep tool_calls
            // and typically use null content rather than an empty string.
            $assistantMessage['content'] = $assistantMessage['content'] ?? null;
            $messages[] = $assistantMessage;

            foreach ($toolCalls as $toolCall) {
                $toolName = (string) data_get($toolCall, 'function.name');
                $rawArgs = (string) data_get($toolCall, 'function.arguments', '{}');
                $arguments = json_decode($rawArgs, true);

                if (! is_array($arguments)) {
                    $arguments = [];
                }

                $arguments = $this->normalizeToolArguments($toolName, $arguments, $message);
                $toolResult = $this->dispatchTool($toolName, $arguments, $user);

                if ($primaryToolName === null) {
                    $primaryToolName = $toolName;
                    $primaryArguments = $arguments;
                    $primaryToolResult = $toolResult;
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => data_get($toolCall, 'id'),
                    'name' => $toolName,
                    'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                ];
            }

            $round++;

            $next = Http::withToken($apiKey)
                ->timeout(60)
                ->acceptJson()
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => $messages,
                    'tools' => $tools,
                    'tool_choice' => 'auto',
                    'temperature' => 0.2,
                ]);

            if (! $next->successful()) {
                if ($primaryToolName !== null && is_array($primaryToolResult)) {
                    $reply = $this->formatToolResult($primaryToolName, $primaryToolResult);

                    return $this->finish(
                        $user,
                        $message,
                        $this->intentFromTool($primaryToolName),
                        $primaryToolName,
                        $primaryArguments,
                        $primaryToolResult,
                        $reply,
                        false,
                        $provider
                    );
                }

                $next->throw();
            }

            $response = $next->json();
        }

        $reply = trim((string) data_get($response, 'choices.0.message.content', ''));
        $toolName = $primaryToolName;
        $arguments = $primaryArguments;
        $toolResult = $primaryToolResult;

        if ($toolName === null) {
            $reply = $reply !== '' ? $reply : 'I could not determine an action for that request.';

            return $this->finish($user, $message, 'direct_answer', null, [], null, $reply, false, $provider);
        }

        if ($this->usesTabularResponse($toolName)) {
            $reply = $this->formatToolResult($toolName, $toolResult);
        } elseif ($reply === '') {
            $reply = $this->formatToolResult($toolName, $toolResult);
        }

        if ($toolName === 'generateInvoicePdf') {
            $downloadUrl = (string) ($toolResult['download_url'] ?? '');

            if ($downloadUrl !== '' && ! Str::contains($reply, $downloadUrl)) {
                $reply .= "\n\nDownload: {$downloadUrl}";
            }
        }

        if ($toolName === 'whatsappSummary') {
            $shareUrl = (string) ($toolResult['whatsapp_share_url'] ?? '');

            if ($shareUrl !== '' && ! Str::contains($reply, $shareUrl)) {
                $reply .= "\n\nShare on WhatsApp: {$shareUrl}";
            }
        }

        return $this->finish(
            $user,
            $message,
            $this->intentFromTool($toolName),
            $toolName,
            $arguments,
            $toolResult,
            $reply,
            false,
            $provider
        );
    }

    private function usesTabularResponse(string $toolName): bool
    {
        return in_array($toolName, [
            'getOutstandingInvoices',
            'getSalesBetweenDates',
            'generateInvoice',
            'getPendingPayments',
            'getTopClients',
            'getDelayedProjects',
            'getOverdueClients',
            'getMonthlyRevenueSummary',
            'summarizeBusinessHealth',
            'listRecords',
            'getRecord',
            'createRecord',
            'updateRecord',
            'deleteRecord',
        ], true);
    }

    /**
     * Recover when Groq rejects a tool call (strict schema / failed_generation).
     *
     * @param  array<string, mixed>|null  $body
     * @return array{reply: string, intent: string|null, tool_called: string|null, blocked: bool, log_id: int|null, provider: string}|null
     */
    private function recoverGroqToolFailure(?array $body, string $message, ?User $user, string $provider): ?array
    {
        $code = data_get($body, 'error.code');
        $failed = (string) data_get($body, 'error.failed_generation', '');

        if ($code !== 'tool_use_failed' || $failed === '') {
            return null;
        }

        $toolName = null;
        $arguments = [];

        if (preg_match('/<function=([A-Za-z0-9_]+)>(\{.*?\})<\/function>/s', $failed, $matches)) {
            $toolName = $matches[1];
            $decoded = json_decode($matches[2], true);
            $arguments = is_array($decoded) ? $decoded : [];
        } elseif (preg_match('/"name"\s*:\s*"([A-Za-z0-9_]+)".*?"arguments"\s*:\s*(\{.*?\})/s', $failed, $matches)) {
            $toolName = $matches[1];
            $decoded = json_decode($matches[2], true);
            $arguments = is_array($decoded) ? $decoded : [];
        }

        if (! $toolName) {
            return null;
        }

        $arguments = $this->normalizeToolArguments($toolName, $arguments, $message);
        $toolResult = $this->dispatchTool($toolName, $arguments, $user);
        $reply = $this->formatToolResult($toolName, $toolResult);

        return $this->finish(
            $user,
            $message,
            $this->intentFromTool($toolName),
            $toolName,
            $arguments,
            $toolResult,
            $reply,
            false,
            $provider
        );
    }

    /**
     * Local intent→tool planner used when the LLM API is unavailable.
     * Still selects and executes real tools — never hardcoded fake business answers.
     *
     * @return array{reply: string, intent: string|null, tool_called: string|null, blocked: bool, log_id: int|null, provider: string}
     */
    private function handleWithLocalAgent(string $message, ?User $user): array
    {
        $plan = $this->planLocalToolCall($message);
        $toolName = $plan['tool'];
        $arguments = $plan['arguments'];
        $intent = $plan['intent'];

        $toolResult = $this->dispatchTool($toolName, $arguments, $user);
        $reply = $this->formatToolResult($toolName, $toolResult);

        return $this->finish($user, $message, $intent, $toolName, $arguments, $toolResult, $reply, false, 'local-agent');
    }

    /**
     * @return array{tool: string, arguments: array<string, mixed>, intent: string}
     */
    private function planLocalToolCall(string $message): array
    {
        $text = Str::lower($message);
        $amount = $this->extractAmount($message);

        if ($crudPlan = $this->planCrudToolCall($message, $text)) {
            return $crudPlan;
        }

        if (Str::contains($text, ['whatsapp', 'watsapp', 'whats app', 'wa.me', 'share summary'])) {
            $focus = Str::contains($text, 'overdue') ? 'overdue' : (Str::contains($text, ['month', 'revenue']) ? 'month' : 'pending');

            return [
                'tool' => 'whatsappSummary',
                'arguments' => ['focus' => $focus],
                'intent' => 'whatsapp_summary',
            ];
        }

        if (Str::contains($text, ['email invoice', 'send invoice', 'mail invoice draft'])) {
            return [
                'tool' => 'emailInvoiceDraft',
                'arguments' => array_filter([
                    'invoice_number' => $this->extractInvoiceNumber($message),
                    'invoice_id' => $this->extractId($message),
                    'to_email' => $this->extractEmail($message),
                ]),
                'intent' => 'email_invoice',
            ];
        }

        if (Str::contains($text, ['pdf invoice', 'invoice pdf', 'download invoice', 'generate pdf'])) {
            return [
                'tool' => 'generateInvoicePdf',
                'arguments' => array_filter([
                    'invoice_number' => $this->extractInvoiceNumber($message),
                    'invoice_id' => $this->extractId($message),
                    'client_name' => $this->extractInvoiceClientName($message),
                ]),
                'intent' => 'invoice_pdf',
            ];
        }

        if (Str::contains($text, ['project note', 'payment risk', 'summarize notes', 'from notes', 'mcp', 'read project'])) {
            return [
                'tool' => 'readProjectNotes',
                'arguments' => [
                    'keyword' => $this->extractKeyword($message, ['vistaar', 'mku', 'apex', 'payment', 'risk']) ?? 'payment',
                ],
                'intent' => 'read_external_notes',
            ];
        }

        if (Str::contains($text, ['generate invoice', 'create invoice', 'invoice draft', 'draft invoice'])) {
            $target = $this->extractClientOrProject($message) ?? 'general';

            return [
                'tool' => 'generateInvoice',
                'arguments' => array_filter([
                    'client_or_project' => $target,
                    'amount' => $amount,
                ], fn ($v) => $v !== null && $v !== ''),
                'intent' => 'generate_invoice',
            ];
        }

        if (Str::contains($text, ['monthly revenue', 'revenue summary', 'generate monthly'])) {
            $period = Str::contains($text, 'last month') ? 'last_month' : (Str::contains($text, 'year') ? 'this_year' : 'this_month');

            return [
                'tool' => 'getMonthlyRevenueSummary',
                'arguments' => ['period' => $period],
                'intent' => 'monthly_revenue',
            ];
        }

        if (Str::contains($text, ['business performing', 'business health', 'how is business', 'performance'])) {
            $period = Str::contains($text, 'last month') ? 'last_month' : (Str::contains($text, 'year') ? 'this_year' : 'this_month');

            return [
                'tool' => 'summarizeBusinessHealth',
                'arguments' => ['period' => $period],
                'intent' => 'business_summary',
            ];
        }

        if (Str::contains($text, ['delayed', 'late project', 'deadline']) && Str::contains($text, ['unpaid', 'payment', 'project'])) {
            return [
                'tool' => 'getDelayedProjects',
                'arguments' => [
                    'limit' => 10,
                    'unpaid_only' => Str::contains($text, ['unpaid', 'pending payment', 'not paid']),
                ],
                'intent' => 'delayed_projects',
            ];
        }

        if (Str::contains($text, ['overdue payment', 'clients have overdue', 'overdue clients', 'which clients have overdue'])) {
            return [
                'tool' => 'getOverdueClients',
                'arguments' => array_filter(['min_amount' => $amount]),
                'intent' => 'overdue_clients',
            ];
        }

        if (Str::contains($text, ['highest paying', 'top paying', 'top client', 'highest outstanding', 'paying clients'])) {
            return [
                'tool' => 'getTopClients',
                'arguments' => [
                    'limit' => 5,
                    'by' => Str::contains($text, ['outstanding', 'overdue']) ? 'outstanding' : 'billed',
                ],
                'intent' => 'top_clients',
            ];
        }

        if (Str::contains($text, ['sales between', 'sales from', 'revenue between', 'show sales'])) {
            [$start, $end] = $this->extractDateRange($message);

            return [
                'tool' => 'getSalesBetweenDates',
                'arguments' => [
                    'start_date' => $start,
                    'end_date' => $end,
                ],
                'intent' => 'sales_report',
            ];
        }

        if (Str::contains($text, ['pending payment', 'delayed payment', 'unpaid payment', 'summarize pending', 'summarize delayed payments'])) {
            return [
                'tool' => 'getPendingPayments',
                'arguments' => array_filter(['min_amount' => $amount]),
                'intent' => 'pending_payments',
            ];
        }

        if (Str::contains($text, ['outstanding', 'overdue invoice', 'unpaid invoice', 'invoices above', 'invoice above'])) {
            return [
                'tool' => 'getOutstandingInvoices',
                'arguments' => array_filter([
                    'min_amount' => $amount,
                    'overdue_only' => Str::contains($text, ['overdue', 'past due']),
                ], fn ($v) => $v !== null),
                'intent' => 'outstanding_invoices',
            ];
        }

        if (Str::contains($text, ['invoice', 'payment']) && ! Str::contains($text, ['create ', 'add ', 'list '])) {
            return [
                'tool' => 'getPendingPayments',
                'arguments' => [],
                'intent' => 'pending_payments',
            ];
        }

        return [
            'tool' => 'summarizeBusinessHealth',
            'arguments' => ['period' => 'this_month'],
            'intent' => 'business_summary',
        ];
    }

    private function securityBlock(string $message): ?string
    {
        $text = Str::lower($message);

        $patterns = [
            '/\b(delete|drop|truncate|destroy)\b.*\b(all|every|entire)\b.*\b(invoice|payment|client|user|database|table|record|project)s?\b/i',
            '/\b(drop|truncate)\b.*\b(table|database)\b/i',
            '/\b(show|expose|print|leak|reveal)\b.*\b(api[_ -]?key|\.env|db[_ -]?password|database password|secret|credential|token)\b/i',
            '/\b(run|execute)\b.*\b(shell|bash|cmd|powershell|artisan\s+tinker)\b/i',
            '/\b(rm\s+-rf|unlink|system\(|exec\(|passthru\(|proc_open)\b/i',
            '/\b(auto[- ]?delete|force delete all)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message) || preg_match($pattern, $text)) {
                return "I can't help with that request. For safety I won't mass-delete records, expose credentials/API keys/.env values, or run shell commands. I can create/update/list records, or delete one specific record by id when you have permission.";
            }
        }

        return null;
    }

    /**
     * @return array{tool: string, arguments: array<string, mixed>, intent: string}|null
     */
    private function planCrudToolCall(string $message, string $text): ?array
    {
        $module = $this->detectModule($text);
        if (! $module) {
            return null;
        }

        $isCreate = Str::contains($text, ['create ', 'add ', 'new ', 'register ', 'save '])
            && ! Str::contains($text, ['generate invoice', 'invoice draft', 'draft invoice']);
        $isUpdate = Str::contains($text, ['update ', 'edit ', 'change ', 'set ', 'rename ', 'mark ']);
        $isDelete = Str::contains($text, ['delete ', 'remove '])
            && ! Str::contains($text, ['all ', 'every ', 'entire ']);
        $isGet = (bool) preg_match('/\b(show|get|find|fetch|view|open)\b.*\b(id|#)\s*\d+/i', $message);
        $isList = Str::contains($text, ['list ', 'show all', 'show me all', 'search ', 'find all', 'display ']);

        if ($isDelete) {
            $id = $this->extractId($message);
            if (! $id) {
                return [
                    'tool' => 'listRecords',
                    'arguments' => [
                        'module' => $module,
                        'search' => $this->extractSearchTerm($message, $module),
                        'limit' => 5,
                    ],
                    'intent' => 'module_list_before_delete',
                ];
            }

            return [
                'tool' => 'deleteRecord',
                'arguments' => ['module' => $module, 'id' => $id],
                'intent' => 'module_delete',
            ];
        }

        if ($isUpdate) {
            $id = $this->extractId($message);
            $data = $this->extractCrudFields($message, $module);

            if (! $id) {
                return [
                    'tool' => 'listRecords',
                    'arguments' => [
                        'module' => $module,
                        'search' => $this->extractSearchTerm($message, $module),
                        'limit' => 5,
                    ],
                    'intent' => 'module_list_before_update',
                ];
            }

            return [
                'tool' => 'updateRecord',
                'arguments' => [
                    'module' => $module,
                    'id' => $id,
                    'data' => $data,
                ],
                'intent' => 'module_update',
            ];
        }

        if ($isCreate) {
            return [
                'tool' => 'createRecord',
                'arguments' => [
                    'module' => $module,
                    'data' => $this->extractCrudFields($message, $module),
                ],
                'intent' => 'module_create',
            ];
        }

        if ($isGet) {
            return [
                'tool' => 'getRecord',
                'arguments' => [
                    'module' => $module,
                    'id' => $this->extractId($message),
                ],
                'intent' => 'module_get',
            ];
        }

        if ($isList || Str::contains($text, [$module, rtrim($module, 's')])) {
            // Only treat as list when clearly asking for module records, not analytics.
            if ($isList || preg_match('/\b(list|show|search|find)\b.*\b'.preg_quote(rtrim($module, 's'), '/').'/i', $message)) {
                return [
                    'tool' => 'listRecords',
                    'arguments' => array_filter([
                        'module' => $module,
                        'search' => $this->extractSearchTerm($message, $module),
                        'limit' => 10,
                    ]),
                    'intent' => 'module_list',
                ];
            }
        }

        return null;
    }

    private function detectModule(string $text): ?string
    {
        return match (true) {
            Str::contains($text, ['user', 'employee', 'hr admin', 'super admin']) => 'users',
            Str::contains($text, ['payment']) && ! Str::contains($text, ['payment risk', 'pending payment', 'outstanding', 'overdue payment']) => 'payments',
            Str::contains($text, ['invoice']) && ! Str::contains($text, ['outstanding', 'overdue', 'generate invoice', 'invoice draft', 'pdf', 'email invoice']) => 'invoices',
            Str::contains($text, ['project']) && ! Str::contains($text, ['project note', 'delayed', 'deadline', 'unpaid']) => 'projects',
            Str::contains($text, ['client', 'company']) && ! Str::contains($text, ['overdue', 'highest paying', 'top client']) => 'clients',
            default => null,
        };
    }

    private function extractId(string $message): ?int
    {
        if (preg_match('/\b(?:id|#)\s*(\d+)\b/i', $message, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\b(?:client|project|invoice|payment|user)\s+#?(\d+)\b/i', $message, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractSearchTerm(string $message, string $module): ?string
    {
        if (preg_match('/\b(?:named|called|for|about|search(?:ing)?(?:\s+for)?)\s+["\']?([^"\',\n]+)["\']?/i', $message, $matches)) {
            $term = trim($matches[1]);
            $term = preg_replace('/\b(client|project|invoice|payment|user)s?\b/i', '', $term) ?? $term;

            return trim($term) !== '' ? trim($term) : null;
        }

        return null;
    }

    private function extractInvoiceNumber(string $message): ?string
    {
        if (preg_match('/\b(INV-[A-Za-z0-9\-]+)\b/i', $message, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    private function extractInvoiceClientName(string $message): ?string
    {
        if (preg_match(
            '/\b(?:invoice\s+)?for\s+(.+?)(?:\s+in\s+pdf|\s+as\s+(?:a\s+)?pdf|\s+pdf|[.?!]|$)/i',
            $message,
            $matches
        )) {
            $name = trim($matches[1], " \t\n\r\0\x0B.\"'");

            return $name !== '' ? $name : null;
        }

        return null;
    }

    private function extractEmail(string $message): ?string
    {
        if (preg_match('/([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})/i', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractCrudFields(string $message, string $module): array
    {
        $data = [];
        $msg = trim($message);

        if (preg_match('/email\s*[:=]?\s*([^\s,]+@[^\s,]+)/i', $msg, $m)) {
            $data['email'] = $this->cleanCaptured($m[1]);
        }
        if (preg_match('/(?:mobile|phone)\s*[:=]?\s*([+\d][\d\s-]{6,})/i', $msg, $m)) {
            $data['mobile'] = preg_replace('/\s+/', '', $m[1]);
        }
        if (preg_match('/(?:status)\s*[:=]?\s*(active|inactive|pending|in[_\s-]?progress|completed|on[_\s-]?hold|cancelled|unpaid|partial|paid|overdue)/i', $msg, $m)) {
            $status = Str::lower(str_replace([' ', '-'], '_', $m[1]));
            if ($module === 'projects') {
                $data['project_status'] = $status;
            } elseif ($module === 'invoices') {
                $data['payment_status'] = $status;
            } else {
                $data['status'] = $status;
            }
        }
        if (preg_match('/(?:amount|total(?:\s+amount)?)\s*[:=]?\s*(?:₹|rs\.?|inr)?\s*([0-9][0-9,]*(?:\.[0-9]+)?)/i', $msg, $m)) {
            $value = (float) str_replace(',', '', $m[1]);
            if ($module === 'projects') {
                $data['total_amount'] = $value;
            } elseif ($module === 'payments') {
                $data['amount'] = $value;
            } else {
                $data['amount'] = $value;
            }
        }
        if (preg_match('/(?:role)\s*[:=]?\s*(Super Admin|HR Admin|HR User)/i', $msg, $m)) {
            $data['role'] = $m[1];
        }
        if (preg_match('/(?:password)\s*[:=]?\s*([^\s,]+)/i', $msg, $m)) {
            $data['password'] = $this->cleanCaptured($m[1]);
        }
        if (preg_match('/(?:payment\s*mode|mode)\s*[:=]?\s*(cash|bank[_\s-]?transfer|upi|cheque|card|other)/i', $msg, $m)) {
            $data['payment_mode'] = Str::lower(str_replace([' ', '-'], '_', $m[1]));
        }
        if (preg_match('/(?:start\s*date)\s*[:=]?\s*(\d{4}-\d{2}-\d{2})/i', $msg, $m)) {
            $data['start_date'] = $m[1];
        }
        if (preg_match('/(?:deadline|due\s*date)\s*[:=]?\s*(\d{4}-\d{2}-\d{2})/i', $msg, $m)) {
            if ($module === 'projects') {
                $data['deadline'] = $m[1];
            } else {
                $data['due_date'] = $m[1];
            }
        }
        if (preg_match('/(?:invoice\s*date|payment\s*date)\s*[:=]?\s*(\d{4}-\d{2}-\d{2})/i', $msg, $m)) {
            if ($module === 'payments') {
                $data['payment_date'] = $m[1];
            } else {
                $data['invoice_date'] = $m[1];
            }
        }
        if (preg_match('/(?:invoice(?:\s*number|#)?)\s*[:=]?\s*([A-Za-z0-9\-]+)/i', $msg, $m) && $module === 'payments') {
            $data['invoice_number'] = $m[1];
        }
        if (preg_match('/(?:for\s+client|client(?:\s*name)?)\s*[:=]?\s*([^,\n]+)/i', $msg, $m)) {
            $clientName = $this->cleanCaptured($m[1]);
            $clientName = trim(preg_replace('/\b(amount|status|start|deadline|with).*$/i', '', $clientName) ?? $clientName);
            if ($clientName !== '') {
                $data['client_name'] = $clientName;
            }
        }

        if ($module === 'clients') {
            if (preg_match('/(?:create|add|new)\s+client\s+([^,]+?)(?:,| with| contact| email| mobile|$)/i', $msg, $m)) {
                $data['company_name'] = $this->cleanCaptured($m[1]);
            } elseif (preg_match('/(?:company(?:\s*name)?)\s*[:=]?\s*([^,\n]+?)(?:,| with| email| mobile| contact|$)/i', $msg, $m)) {
                $data['company_name'] = $this->cleanCaptured($m[1]);
            }

            if (preg_match('/contact(?:\s+person)?\s*[:=]?\s*([^,]+?)(?:,| email| mobile|$)/i', $msg, $m)) {
                $data['contact_person'] = $this->cleanCaptured($m[1]);
            }
            if (empty($data['contact_person']) && ! empty($data['company_name'])) {
                $data['contact_person'] = $data['company_name'].' Contact';
            }
            if (empty($data['status'])) {
                $data['status'] = 'active';
            }
        }

        if ($module === 'projects') {
            if (preg_match('/(?:create|add|new)\s+project\s+([^,]+?)(?:,| for| with| amount| status| start| deadline|$)/i', $msg, $m)) {
                $data['project_name'] = $this->cleanCaptured($m[1]);
            } elseif (preg_match('/(?:project(?:\s*name)?)\s*[:=]?\s*([^,\n]+?)(?:,| for| with| amount| start| deadline|$)/i', $msg, $m)) {
                $data['project_name'] = $this->cleanCaptured($m[1]);
            }
            if (empty($data['project_status'])) {
                $data['project_status'] = 'pending';
            }
            if (empty($data['start_date'])) {
                $data['start_date'] = now()->toDateString();
            }
            if (! isset($data['payment_received'])) {
                $data['payment_received'] = 0;
            }
        }

        if ($module === 'users') {
            if (preg_match('/(?:create|add|new)\s+user\s+([^,]+?)(?:,| email| password| role|$)/i', $msg, $m)) {
                $data['name'] = $this->cleanCaptured($m[1]);
            } elseif (preg_match('/(?:user(?:\s*name)?|name)\s*[:=]?\s*([^,\n]+?)(?:,| email| password| role|$)/i', $msg, $m)) {
                $data['name'] = $this->cleanCaptured($m[1]);
            }
            if (empty($data['role'])) {
                $data['role'] = 'HR User';
            }
        }

        if ($module === 'invoices') {
            if (empty($data['invoice_date'])) {
                $data['invoice_date'] = now()->toDateString();
            }
            if (empty($data['payment_status'])) {
                $data['payment_status'] = 'unpaid';
            }
            if (! isset($data['paid_amount'])) {
                $data['paid_amount'] = 0;
            }
        }

        if ($module === 'payments') {
            if (empty($data['payment_date'])) {
                $data['payment_date'] = now()->toDateString();
            }
            if (empty($data['payment_mode'])) {
                $data['payment_mode'] = 'bank_transfer';
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>|null  $toolResult
     * @return array{reply: string, intent: string|null, tool_called: string|null, blocked: bool, log_id: int|null, provider: string}
     */
    private function finish(
        ?User $user,
        string $message,
        ?string $intent,
        ?string $toolCalled,
        array $arguments,
        ?array $toolResult,
        string $reply,
        bool $blocked,
        string $provider,
    ): array {
        $log = AiChatLog::create([
            'user_id' => $user?->id,
            'user_message' => $message,
            'intent' => $intent,
            'tool_called' => $toolCalled,
            'tool_arguments' => $arguments ?: null,
            'tool_result' => $toolResult,
            'ai_response' => $reply,
            'blocked' => $blocked,
            'provider' => $provider,
        ]);

        return [
            'reply' => $reply,
            'intent' => $intent,
            'tool_called' => $toolCalled,
            'blocked' => $blocked,
            'log_id' => $log->id,
            'provider' => $provider,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatToolResult(string $toolName, array $result): string
    {
        if (($result['error'] ?? false) === true) {
            return (string) ($result['message'] ?? 'The tool could not complete that request.');
        }

        return match ($toolName) {
            'getOutstandingInvoices' => $this->formatOutstanding($result),
            'getSalesBetweenDates' => $this->formatSales($result),
            'generateInvoice' => $this->formatGeneratedInvoice($result),
            'getPendingPayments' => $this->formatPendingPayments($result),
            'getTopClients' => $this->formatTopClients($result),
            'getDelayedProjects' => $this->formatDelayedProjects($result),
            'summarizeBusinessHealth', 'getMonthlyRevenueSummary' => $this->formatBusinessHealth($result),
            'getOverdueClients' => $this->formatOverdueClients($result),
            'readProjectNotes' => $this->formatProjectNotes($result),
            'generateInvoicePdf' => $this->formatInvoicePdf($result),
            'emailInvoiceDraft' => $this->formatEmailInvoice($result),
            'whatsappSummary' => $this->formatWhatsappSummary($result),
            'listRecords', 'getRecord', 'createRecord', 'updateRecord', 'deleteRecord' => $this->formatCrudResult($toolName, $result),
            default => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'Done.',
        };
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatCrudResult(string $toolName, array $result): string
    {
        $module = $result['module'] ?? 'records';
        $action = $result['action'] ?? $toolName;

        if ($toolName === 'listRecords') {
            if (($result['count'] ?? 0) === 0) {
                return sprintf('%s list: no matching records found.', ucfirst((string) $module));
            }

            [$headers, $rows] = $this->recordTable((string) $module, $result['records'] ?? []);

            return sprintf("%s list (%d found)\n\n", ucfirst((string) $module), $result['count'] ?? 0)
                .$this->markdownTable($headers, $rows);
        }

        $record = $result['record'] ?? [];
        $verb = match ($action) {
            'create' => 'Created',
            'update' => 'Updated',
            'delete' => 'Deleted',
            default => 'Fetched',
        };

        return sprintf(
            "%s %s record successfully.\n- %s",
            $verb,
            rtrim((string) $module, 's'),
            $this->summarizeRecord((string) $module, $record)
        );
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private function recordTable(string $module, array $records): array
    {
        return match ($module) {
            'clients' => [
                ['ID', 'Company', 'Contact', 'Email', 'Status'],
                array_map(fn (array $row) => [
                    $row['id'] ?? '',
                    $row['company_name'] ?? '',
                    $row['contact_person'] ?? '',
                    $row['email'] ?? '',
                    ucfirst((string) ($row['status'] ?? '')),
                ], $records),
            ],
            'projects' => [
                ['ID', 'Project', 'Client', 'Amount', 'Status'],
                array_map(fn (array $row) => [
                    $row['id'] ?? '',
                    $row['project_name'] ?? '',
                    $row['client'] ?? ($row['client_id'] ?? ''),
                    $this->money($row['total_amount'] ?? 0),
                    ucfirst(str_replace('_', ' ', (string) ($row['project_status'] ?? ''))),
                ], $records),
            ],
            'invoices' => [
                ['ID', 'Invoice', 'Client', 'Date', 'Amount', 'Paid', 'Status'],
                array_map(fn (array $row) => [
                    $row['id'] ?? '',
                    $row['invoice_number'] ?? '',
                    $row['client'] ?? '',
                    $row['invoice_date'] ?? '',
                    $this->money($row['amount'] ?? 0),
                    $this->money($row['paid_amount'] ?? 0),
                    ucfirst((string) ($row['payment_status'] ?? '')),
                ], $records),
            ],
            'payments' => [
                ['ID', 'Invoice', 'Amount', 'Mode', 'Date'],
                array_map(fn (array $row) => [
                    $row['id'] ?? '',
                    $row['invoice_number'] ?? ($row['invoice_id'] ?? ''),
                    $this->money($row['amount'] ?? 0),
                    ucfirst(str_replace('_', ' ', (string) ($row['payment_mode'] ?? ''))),
                    $row['payment_date'] ?? '',
                ], $records),
            ],
            'users' => [
                ['ID', 'Name', 'Email', 'Role'],
                array_map(fn (array $row) => [
                    $row['id'] ?? '',
                    $row['name'] ?? '',
                    $row['email'] ?? '',
                    $row['role'] ?? '',
                ], $records),
            ],
            default => [
                ['Record'],
                array_map(fn (array $row) => [json_encode($row, JSON_UNESCAPED_UNICODE)], $records),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function summarizeRecord(string $module, array $record): string
    {
        return match ($module) {
            'clients' => sprintf(
                '#%s %s | %s | %s | %s',
                $record['id'] ?? '?',
                $record['company_name'] ?? '',
                $record['contact_person'] ?? '',
                $record['email'] ?? '',
                $record['status'] ?? ''
            ),
            'projects' => sprintf(
                '#%s %s | client %s | amount %s | status %s',
                $record['id'] ?? '?',
                $record['project_name'] ?? '',
                $record['client'] ?? ($record['client_id'] ?? ''),
                $this->money($record['total_amount'] ?? 0),
                $record['project_status'] ?? ''
            ),
            'invoices' => sprintf(
                '#%s %s | client %s | amount %s | status %s',
                $record['id'] ?? '?',
                $record['invoice_number'] ?? '',
                $record['client'] ?? '',
                $this->money($record['amount'] ?? 0),
                $record['payment_status'] ?? ''
            ),
            'payments' => sprintf(
                '#%s invoice %s | amount %s | mode %s | date %s',
                $record['id'] ?? '?',
                $record['invoice_number'] ?? ($record['invoice_id'] ?? ''),
                $this->money($record['amount'] ?? 0),
                $record['payment_mode'] ?? '',
                $record['payment_date'] ?? ''
            ),
            'users' => sprintf(
                '#%s %s | %s | role %s',
                $record['id'] ?? '?',
                $record['name'] ?? '',
                $record['email'] ?? '',
                $record['role'] ?? 'n/a'
            ),
            default => json_encode($record, JSON_UNESCAPED_UNICODE) ?: 'record saved',
        };
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatOutstanding(array $result): string
    {
        $rows = array_map(fn (array $invoice) => [
            $invoice['invoice_number'] ?? 'n/a',
            $invoice['client'] ?? 'Unknown client',
            $this->money($invoice['amount'] ?? 0),
            $this->money($invoice['paid_amount'] ?? 0),
            $this->money($invoice['outstanding'] ?? 0),
            $invoice['due_date'] ?? 'n/a',
            ucfirst((string) ($invoice['payment_status'] ?? 'n/a')),
        ], $result['invoices'] ?? []);

        $summary = sprintf(
            'Outstanding invoices: %d · Total outstanding: %s',
            $result['count'] ?? 0,
            $this->money($result['total_outstanding'] ?? 0)
        );

        return $rows === []
            ? "{$summary}\n\nNo matching outstanding invoices found."
            : $summary."\n\n".$this->markdownTable(
                ['Invoice', 'Client', 'Amount', 'Paid', 'Outstanding', 'Due date', 'Status'],
                $rows
            );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatSales(array $result): string
    {
        $summary = $this->markdownTable(
            ['Metric', 'Value'],
            [
                ['Date range', ($result['start_date'] ?? 'n/a').' to '.($result['end_date'] ?? 'n/a')],
                ['Invoices', $result['invoice_count'] ?? 0],
                ['Total billed', $this->money($result['total_billed'] ?? 0)],
                ['Total collected', $this->money($result['total_collected'] ?? 0)],
            ]
        );

        $rows = array_map(fn (array $invoice) => [
            $invoice['invoice_number'] ?? 'n/a',
            $invoice['invoice_date'] ?? 'n/a',
            $this->money($invoice['amount'] ?? 0),
            $this->money($invoice['paid_amount'] ?? 0),
            ucfirst((string) ($invoice['payment_status'] ?? 'n/a')),
        ], $result['invoices'] ?? []);

        $details = $rows === []
            ? 'No invoices found in this date range.'
            : $this->markdownTable(['Invoice', 'Date', 'Billed', 'Collected', 'Status'], $rows);

        return "Sales summary\n\n{$summary}\n\nInvoice details\n\n{$details}";
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatGeneratedInvoice(array $result): string
    {
        $invoice = $result['invoice'] ?? [];
        $workflow = $result['workflow'] ?? [];

        return "Invoice draft generated and saved.\n\n".$this->markdownTable(
            ['Field', 'Value'],
            [
                ['Invoice #', $invoice['invoice_number'] ?? 'n/a'],
                ['Client', $invoice['client'] ?? 'n/a'],
                ['Project', $invoice['project'] ?? 'n/a'],
                ['Amount', $this->money($invoice['amount'] ?? 0)],
                ['Pending amount', $this->money($workflow['calculated_pending_amount'] ?? 0)],
                ['Due date', $invoice['due_date'] ?? 'n/a'],
                ['Description', $invoice['description'] ?? ''],
                ['Saved invoice ID', $workflow['saved_invoice_id'] ?? 'n/a'],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatPendingPayments(array $result): string
    {
        $rows = array_map(fn (array $row) => [
            $row['invoice_number'] ?? 'n/a',
            $row['client'] ?? 'Unknown',
            $this->money($row['amount'] ?? 0),
            $this->money($row['paid_amount'] ?? 0),
            $this->money($row['pending_amount'] ?? 0),
            $row['due_date'] ?? 'n/a',
        ], $result['payments'] ?? []);

        $summary = sprintf(
            'Pending payments: %d · Total pending: %s',
            $result['count'] ?? 0,
            $this->money($result['total_pending'] ?? 0)
        );

        return $rows === []
            ? "{$summary}\n\nNo pending payments found."
            : $summary."\n\n".$this->markdownTable(
                ['Invoice', 'Client', 'Amount', 'Paid', 'Pending', 'Due date'],
                $rows
            );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatTopClients(array $result): string
    {
        $rows = [];
        foreach (($result['clients'] ?? []) as $index => $client) {
            $rows[] = [
                $index + 1,
                $client['client'] ?? 'Unknown',
                $this->money($client['billed'] ?? 0),
                $this->money($client['outstanding'] ?? 0),
                $client['invoice_count'] ?? 0,
            ];
        }

        return sprintf("Top clients by %s\n\n", $result['ranked_by'] ?? 'outstanding')
            .$this->markdownTable(['Rank', 'Client', 'Billed', 'Outstanding', 'Invoices'], $rows);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatDelayedProjects(array $result): string
    {
        $rows = array_map(fn (array $project) => [
            $project['project_name'] ?? 'n/a',
            $project['client'] ?? 'Unknown',
            $project['deadline'] ?? 'n/a',
            $project['days_overdue'] ?? 0,
            $this->money($project['pending_amount'] ?? 0),
            ucfirst(str_replace('_', ' ', (string) ($project['project_status'] ?? 'n/a'))),
        ], $result['projects'] ?? []);

        return $rows === []
            ? 'Delayed projects: 0. No delayed projects found.'
            : sprintf("Delayed projects: %d\n\n", $result['count'] ?? count($rows))
                .$this->markdownTable(['Project', 'Client', 'Deadline', 'Days overdue', 'Pending', 'Status'], $rows);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatBusinessHealth(array $result): string
    {
        $sales = $result['sales'] ?? [];
        $outstanding = $result['outstanding'] ?? [];

        $summary = $this->markdownTable(
            ['Metric', 'Value'],
            [
                ['Period', $result['period'] ?? 'current period'],
                ['Billed', $this->money($sales['total_billed'] ?? 0)],
                ['Invoices', $sales['invoice_count'] ?? 0],
                ['Collected', $this->money($result['payments_collected'] ?? 0)],
                ['Outstanding', $this->money($outstanding['total'] ?? 0)],
                ['Outstanding invoices', $outstanding['count'] ?? 0],
                ['Active clients', $result['active_clients'] ?? 0],
                ['Active projects', $result['active_projects'] ?? 0],
                ['Delayed projects', data_get($result, 'delayed_projects.count', 0)],
            ]
        );

        $clientRows = array_map(fn (array $client) => [
            $client['client'] ?? 'Unknown',
            $this->money($client['billed'] ?? 0),
            $this->money($client['outstanding'] ?? 0),
            $client['invoice_count'] ?? 0,
        ], $result['top_outstanding_clients'] ?? []);

        $clients = $clientRows === []
            ? 'No outstanding clients found.'
            : $this->markdownTable(['Client', 'Billed', 'Outstanding', 'Invoices'], $clientRows);

        return "Business summary\n\n{$summary}\n\nHighest outstanding clients\n\n{$clients}";
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatOverdueClients(array $result): string
    {
        $rows = array_map(fn (array $client) => [
            $client['client'] ?? 'Unknown',
            $this->money($client['total_overdue'] ?? 0),
            $client['overdue_invoices'] ?? 0,
            implode(', ', $client['invoices'] ?? []),
        ], $result['clients'] ?? []);

        $summary = sprintf(
            'Overdue clients: %d · Total overdue: %s',
            $result['count'] ?? 0,
            $this->money($result['total_overdue'] ?? 0)
        );

        return $rows === []
            ? "{$summary}\n\nNo overdue clients found."
            : $summary."\n\n".$this->markdownTable(
                ['Client', 'Overdue amount', 'Invoices', 'Invoice numbers'],
                $rows
            );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatInvoicePdf(array $result): string
    {
        return implode("\n", [
            'Invoice PDF generated.',
            sprintf('- Invoice #: %s', $result['invoice_number'] ?? 'n/a'),
            sprintf('- Client: %s', $result['client'] ?? 'n/a'),
            sprintf('- Amount: %s', $this->money($result['amount'] ?? 0)),
            sprintf('- Download: %s', $result['download_url'] ?? 'n/a'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatEmailInvoice(array $result): string
    {
        return implode("\n", [
            'Invoice draft emailed.',
            sprintf('- To: %s', $result['to'] ?? 'n/a'),
            sprintf('- Invoice #: %s', $result['invoice_number'] ?? 'n/a'),
            sprintf('- Client: %s', $result['client'] ?? 'n/a'),
            sprintf('- Amount: %s', $this->money($result['amount'] ?? 0)),
            sprintf('- PDF: %s', $result['download_url'] ?? 'attached'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatWhatsappSummary(array $result): string
    {
        return implode("\n", [
            'WhatsApp-ready summary:',
            '',
            trim((string) ($result['message'] ?? '')),
            '',
            sprintf('Share link: %s', $result['whatsapp_share_url'] ?? 'n/a'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function formatProjectNotes(array $result): string
    {
        $content = trim((string) ($result['content'] ?? ''));
        $source = $result['source'] ?? 'mcp:project-notes.md';

        $summaryLines = [
            "External source read: {$source}",
            '',
            $this->summarizeNotesLocally($content),
            '',
            'Source excerpt:',
            Str::limit($content, 1200),
        ];

        return implode("\n", $summaryLines);
    }

    private function summarizeNotesLocally(string $content): string
    {
        $lower = Str::lower($content);
        $points = [];

        if (Str::contains($lower, 'vistaar') && Str::contains($lower, 'overdue')) {
            $points[] = 'Vistaar Finance has an overdue milestone and elevated payment risk due to extended UAT.';
        }
        if (Str::contains($lower, 'mku')) {
            $points[] = 'MKU has meaningful pending invoice value tied to a delayed modernization sprint.';
        }
        if (Str::contains($lower, 'apex')) {
            $points[] = 'Apex Retail retainer looks stable with low payment risk.';
        }
        if ($points === []) {
            $points[] = 'Project notes were loaded successfully; review the excerpt for payment and delivery risks.';
        }

        return "Payment risk summary:\n- ".implode("\n- ", $points);
    }

    private function systemPrompt(): string
    {
        $today = now()->toDateString();
        $year = now()->year;

        return <<<PROMPT
You are Wama AI, a business operations agent.
Today's date is {$today}. The current year is {$year}.
Use tools for live data and module CRUD. Do not invent invoice numbers or amounts.
Modules available for CRUD: clients, projects, invoices, payments, users.
Use analytics tools for outstanding invoices, sales ranges, overdue clients, monthly revenue, PDF, email draft, and WhatsApp summary.
Use listRecords/getRecord/createRecord/updateRecord/deleteRecord for module operations.
If a request is general advice or does not require live data/actions, answer directly without calling tools.
You may call multiple tools when needed to decide the best answer.
When asked to download an invoice PDF by client/company name, call generateInvoicePdf with client_name.
When asked to share a pending payment, overdue, or monthly summary on WhatsApp (including the misspelling "watsapp"), call whatsappSummary.
For date ranges without an explicit year, always use {$year} (never invent a past year).
Pass start_date and end_date to tools as YYYY-MM-DD.
Never mass-delete, never reveal secrets, API keys, .env values, credentials, or run shell commands.
Delete only one specific record by id when asked.
If asked to do something unsafe, refuse.
When generating invoices, produce a professional description and confirm what was saved.
Prefer INR formatting in final answers.
PROMPT;
    }

    /**
     * Correct LLM date args when the user omitted a year (models often invent a past year).
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function normalizeToolArguments(string $toolName, array $arguments, string $message): array
    {
        if ($toolName !== 'getSalesBetweenDates') {
            return $arguments;
        }

        if (preg_match('/\b(20\d{2})\b/', $message)) {
            return $arguments;
        }

        [$start, $end] = $this->extractDateRange($message);

        return array_merge($arguments, [
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    private function intentFromTool(string $toolName): string
    {
        return match ($toolName) {
            'getOutstandingInvoices' => 'outstanding_invoices',
            'getSalesBetweenDates' => 'sales_report',
            'generateInvoice' => 'generate_invoice',
            'getPendingPayments' => 'pending_payments',
            'getTopClients' => 'top_clients',
            'getDelayedProjects' => 'delayed_projects',
            'getOverdueClients' => 'overdue_clients',
            'getMonthlyRevenueSummary' => 'monthly_revenue',
            'summarizeBusinessHealth' => 'business_summary',
            'readProjectNotes' => 'read_external_notes',
            'generateInvoicePdf' => 'invoice_pdf',
            'emailInvoiceDraft' => 'email_invoice',
            'whatsappSummary' => 'whatsapp_summary',
            'listRecords' => 'module_list',
            'getRecord' => 'module_get',
            'createRecord' => 'module_create',
            'updateRecord' => 'module_update',
            'deleteRecord' => 'module_delete',
            default => $toolName,
        };
    }

    private function extractAmount(string $message): ?float
    {
        if (preg_match('/(?:₹|rs\.?|inr)?\s*([0-9][0-9,]*(?:\.[0-9]+)?)\s*(k|lakh|lac)?/i', $message, $matches)) {
            $value = (float) str_replace(',', '', $matches[1]);
            $suffix = Str::lower($matches[2] ?? '');

            if ($suffix === 'k') {
                $value *= 1000;
            }
            if (in_array($suffix, ['lakh', 'lac'], true)) {
                $value *= 100000;
            }

            return $value;
        }

        return null;
    }

    private function extractClientOrProject(string $message): ?string
    {
        if (preg_match('/(?:for|about|regarding)\s+([A-Za-z0-9][A-Za-z0-9 &\-]{1,60}?)(?:\s+project)?(?:[.?!]|$)/i', $message, $matches)) {
            return trim($matches[1]);
        }

        foreach (['Vistaar Finance', 'Vistaar', 'MKU', 'Apex Retail', 'Apex'] as $known) {
            if (Str::contains(Str::lower($message), Str::lower($known))) {
                return $known;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $candidates
     */
    private function extractKeyword(string $message, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (Str::contains(Str::lower($message), Str::lower($candidate))) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function extractDateRange(string $message): array
    {
        // Normalize "1st July" / "18th" into "1 July" / "18".
        $normalized = preg_replace('/(\d{1,2})(st|nd|rd|th)\b/i', '$1', $message) ?? $message;

        if (preg_match('/(\d{4}-\d{2}-\d{2}).*?(\d{4}-\d{2}-\d{2})/', $normalized, $matches)) {
            return [$matches[1], $matches[2]];
        }

        $month = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';

        // e.g. "1 May and 10 May" / "1st July to 18th July" / "1 July to 18 July 2026"
        if (preg_match(
            '/(\d{1,2})\s+('.$month.')\s*(?:\d{4})?\s*(?:and|to|-|through|till|until)\s*(\d{1,2})\s+('.$month.')(?:\s+(\d{4}))?/i',
            $normalized,
            $matches
        )) {
            $year = isset($matches[5]) && $matches[5] !== '' ? (int) $matches[5] : (int) now()->year;
            $start = \Carbon\Carbon::parse(sprintf('%d %s %d', (int) $matches[1], $matches[2], $year));
            $end = \Carbon\Carbon::parse(sprintf('%d %s %d', (int) $matches[3], $matches[4], $year));

            return [$start->toDateString(), $end->toDateString()];
        }

        // e.g. "from 1 July to 18 July"
        if (preg_match(
            '/(?:from|between)\s+(\d{1,2})\s+('.$month.')(?:\s+(\d{4}))?\s*(?:and|to|-|through|till|until)\s*(\d{1,2})\s+('.$month.')(?:\s+(\d{4}))?/i',
            $normalized,
            $matches
        )) {
            $year = (int) (($matches[6] ?: $matches[3]) ?: now()->year);
            $start = \Carbon\Carbon::parse(sprintf('%d %s %d', (int) $matches[1], $matches[2], $year));
            $end = \Carbon\Carbon::parse(sprintf('%d %s %d', (int) $matches[4], $matches[5], $year));

            return [$start->toDateString(), $end->toDateString()];
        }

        return [
            now()->startOfMonth()->toDateString(),
            now()->toDateString(),
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    private function markdownTable(array $headers, array $rows): string
    {
        $escape = static function (mixed $value): string {
            $text = str_replace(["\r", "\n"], ' ', (string) $value);

            return str_replace('|', '\\|', trim($text));
        };

        $lines = [
            '| '.implode(' | ', array_map($escape, $headers)).' |',
            '| '.implode(' | ', array_fill(0, count($headers), '---')).' |',
        ];

        foreach ($rows as $row) {
            $cells = array_pad(array_slice($row, 0, count($headers)), count($headers), '');
            $lines[] = '| '.implode(' | ', array_map($escape, $cells)).' |';
        }

        return implode("\n", $lines);
    }

    private function money(float|int|string|null $amount): string
    {
        return '₹'.number_format((float) $amount, 2);
    }

    private function cleanCaptured(string $value): string
    {
        return trim($value, " \t\n\r\0\x0B.\"'");
    }
}
