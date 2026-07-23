<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'user_message',
    'intent',
    'tool_called',
    'tool_arguments',
    'tool_result',
    'ai_response',
    'blocked',
    'provider',
])]
class AiChatLog extends Model
{
    protected function casts(): array
    {
        return [
            'tool_arguments' => 'array',
            'tool_result' => 'array',
            'blocked' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
