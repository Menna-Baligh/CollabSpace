<?php

use App\Http\Controllers\Api\V1\Chats\ChatAttachmentController;
use App\Http\Controllers\Api\V1\Chats\ConversationController;
use Illuminate\Support\Facades\Route;

Route::prefix('conversations')->name('conversations.')->group(function () {
    Route::get('/', [ConversationController::class, 'index'])->name('index');
    Route::post('/direct', [ConversationController::class, 'storeDirect'])->name('direct');
    Route::post('/project-channel', [ConversationController::class, 'storeProjectChannel'])->name('project-channel');
    Route::post('/team-channel', [ConversationController::class, 'storeTeamChannel'])->name('team-channel');

    Route::prefix('{conversation}')->group(function () {
        Route::get('/', [ConversationController::class, 'show'])->name('show');
        Route::get('/participants', [ConversationController::class, 'participants'])->name('participants');
        Route::get('/messages', [ConversationController::class, 'messages'])->name('messages');
        Route::post('/messages', [ConversationController::class, 'sendMessage'])
            ->middleware('throttle:chat-messages')
            ->name('send-message');
        Route::patch('/messages/{message}', [ConversationController::class, 'updateMessage'])->name('update-message');
        Route::delete('/messages/{message}', [ConversationController::class, 'deleteMessage'])->name('delete-message');
        Route::post('/read', [ConversationController::class, 'markAsRead'])->name('read');
        Route::post('/typing', [ConversationController::class, 'typing'])->name('typing');

        Route::get('/threads', [ConversationController::class, 'listThreads'])->name('threads');
        Route::post('/threads', [ConversationController::class, 'storeThread'])->name('store-thread');

        Route::post('/calls', [ConversationController::class, 'initiateCall'])->name('calls.initiate');
    });
});

Route::prefix('threads/{thread}')->name('threads.')->group(function () {
    Route::get('/', [ConversationController::class, 'showThread'])->name('show');
    Route::get('/replies', [ConversationController::class, 'threadReplies'])->name('replies');
    Route::post('/replies', [ConversationController::class, 'addThreadReply'])
        ->middleware('throttle:chat-messages')
        ->name('add-reply');
    Route::patch('/replies/{reply}', [ConversationController::class, 'updateThreadReply'])->name('update-reply');
    Route::delete('/replies/{reply}', [ConversationController::class, 'deleteThreadReply'])->name('delete-reply');
});

Route::prefix('calls/{call}')->name('calls.')->group(function () {
    Route::get('/join', [ConversationController::class, 'joinCall'])->name('join');
    Route::post('/end', [ConversationController::class, 'endCall'])->name('end');
});

Route::get('/chat-attachments/{attachment}/download', [ChatAttachmentController::class, 'download'])
    ->name('chat-attachments.download');
