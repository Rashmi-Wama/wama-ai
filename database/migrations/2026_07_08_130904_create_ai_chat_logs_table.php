<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('user_message');
            $table->string('intent')->nullable();
            $table->string('tool_called')->nullable();
            $table->json('tool_arguments')->nullable();
            $table->json('tool_result')->nullable();
            $table->longText('ai_response')->nullable();
            $table->boolean('blocked')->default(false);
            $table->string('provider')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_logs');
    }
};
