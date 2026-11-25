<?php

use App\Http\Controllers\Api\V1\Ai\AiChatController;
use App\Http\Controllers\Api\V1\Ai\AiConversationController;
use Illuminate\Support\Facades\Route;

Route::prefix('ai')->name('ai.')->group(function () {
    Route::get('conversations', [AiConversationController::class, 'index'])->name('conversations.index');
    Route::get('conversations/{conversation}', [AiConversationController::class, 'show'])->name('conversations.show');
    Route::delete('conversations/{conversation}', [AiConversationController::class, 'destroy'])->name('conversations.destroy');
    Route::post('chat', [AiChatController::class, 'send'])->name('chat.send');
    Route::post('messages/{message}/regenerate', [AiChatController::class, 'regenerate'])->name('messages.regenerate');
    Route::patch('messages/{message}/feedback', [AiChatController::class, 'feedback'])->name('messages.feedback');
});
