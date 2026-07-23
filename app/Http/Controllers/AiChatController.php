<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Services\Ai\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = AiConversation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'last_message_at', 'created_at']);

        $activeId = $request->integer('conversation');
        $active = null;

        if ($activeId) {
            $active = AiConversation::query()
                ->where('user_id', $user->id)
                ->with(['messages' => fn ($q) => $q->orderBy('id')])
                ->find($activeId);
        }

        if (! $active && $conversations->isNotEmpty()) {
            $active = AiConversation::query()
                ->where('user_id', $user->id)
                ->with(['messages' => fn ($q) => $q->orderBy('id')])
                ->find($conversations->first()->id);
        }

        return Inertia::render('Dashboard', [
            'conversations' => $conversations->map(fn (AiConversation $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'last_message_at' => optional($c->last_message_at)?->toIso8601String(),
            ]),
            'activeConversation' => $active ? [
                'id' => $active->id,
                'title' => $active->title,
                'messages' => $active->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'content' => $m->content,
                    'created_at' => optional($m->created_at)?->toIso8601String(),
                ]),
            ] : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $conversation = AiConversation::create([
            'user_id' => $request->user()->id,
            'title' => 'New chat',
            'last_message_at' => now(),
        ]);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'last_message_at' => optional($conversation->last_message_at)?->toIso8601String(),
                'messages' => [],
            ],
        ]);
    }

    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        $conversation->load(['messages' => fn ($q) => $q->orderBy('id')]);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'messages' => $conversation->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'content' => $m->content,
                    'created_at' => optional($m->created_at)?->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function destroy(Request $request, AiConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        $conversation->delete();

        return response()->json(['deleted' => true]);
    }

    public function chat(Request $request, AiChatService $aiChat): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
        ]);

        $user = $request->user();
        $conversation = null;

        if (! empty($data['conversation_id'])) {
            $conversation = AiConversation::query()
                ->where('user_id', $user->id)
                ->findOrFail($data['conversation_id']);
        } else {
            $conversation = AiConversation::create([
                'user_id' => $user->id,
                'title' => 'New chat',
                'last_message_at' => now(),
            ]);
        }

        $isFirstMessage = $conversation->messages()->count() === 0;

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $data['message'],
        ]);

        if ($isFirstMessage || $conversation->title === 'New chat') {
            $conversation->title = Str::limit(trim($data['message']), 42, '…');
        }

        $result = $aiChat->handle($data['message'], $user);

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['reply'],
            'intent' => $result['intent'],
            'tool_called' => $result['tool_called'],
        ]);

        $conversation->last_message_at = now();
        $conversation->save();

        return response()->json([
            'reply' => $result['reply'],
            'intent' => $result['intent'],
            'tool_called' => $result['tool_called'],
            'blocked' => $result['blocked'],
            'provider' => $result['provider'],
            'log_id' => $result['log_id'],
            'timestamp' => now()->toIso8601String(),
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'last_message_at' => optional($conversation->last_message_at)?->toIso8601String(),
            ],
            'messages' => [
                [
                    'id' => $userMessage->id,
                    'role' => $userMessage->role,
                    'content' => $userMessage->content,
                ],
                [
                    'id' => $assistantMessage->id,
                    'role' => $assistantMessage->role,
                    'content' => $assistantMessage->content,
                ],
            ],
        ]);
    }

    public function stream(Request $request, AiChatService $aiChat): StreamedResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
        ]);

        $user = $request->user();
        $conversation = null;

        if (! empty($data['conversation_id'])) {
            $conversation = AiConversation::query()
                ->where('user_id', $user->id)
                ->findOrFail($data['conversation_id']);
        } else {
            $conversation = AiConversation::create([
                'user_id' => $user->id,
                'title' => 'New chat',
                'last_message_at' => now(),
            ]);
        }

        $isFirstMessage = $conversation->messages()->count() === 0;

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $data['message'],
        ]);

        if ($isFirstMessage || $conversation->title === 'New chat') {
            $conversation->title = Str::limit(trim($data['message']), 42, '…');
        }

        $result = $aiChat->handle($data['message'], $user);
        $reply = (string) ($result['reply'] ?? '');

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply,
            'intent' => $result['intent'],
            'tool_called' => $result['tool_called'],
        ]);

        $conversation->last_message_at = now();
        $conversation->save();

        return response()->stream(function () use ($reply, $result, $conversation, $userMessage, $assistantMessage) {
            $meta = [
                'type' => 'meta',
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'last_message_at' => optional($conversation->last_message_at)?->toIso8601String(),
                ],
                'user_message' => [
                    'id' => $userMessage->id,
                    'role' => $userMessage->role,
                    'content' => $userMessage->content,
                ],
                'assistant_message_id' => $assistantMessage->id,
                'intent' => $result['intent'],
                'tool_called' => $result['tool_called'],
                'blocked' => $result['blocked'],
                'provider' => $result['provider'],
            ];

            echo 'data: '.json_encode($meta)."\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            $chunks = preg_split('/(?<=\s)/u', $reply) ?: [$reply];
            $buffer = '';

            foreach ($chunks as $chunk) {
                $buffer .= $chunk;
                echo 'data: '.json_encode([
                    'type' => 'delta',
                    'content' => $chunk,
                ])."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                usleep(18000);
            }

            echo 'data: '.json_encode([
                'type' => 'done',
                'reply' => $buffer !== '' ? $buffer : $reply,
                'messages' => [
                    [
                        'id' => $userMessage->id,
                        'role' => $userMessage->role,
                        'content' => $userMessage->content,
                    ],
                    [
                        'id' => $assistantMessage->id,
                        'role' => $assistantMessage->role,
                        'content' => $assistantMessage->content,
                    ],
                ],
            ])."\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
