<?php

use App\Http\Controllers\Api\V1\Meetings\MeetingController;
use Illuminate\Support\Facades\Route;

Route::prefix('meetings')->name('meetings.')->group(function () {
    Route::get('/', [MeetingController::class, 'index'])->name('index');
    Route::post('/', [MeetingController::class, 'store'])->name('store');

    Route::prefix('{meeting}')->group(function () {
        Route::get('/', [MeetingController::class, 'show'])->name('show');
        Route::patch('/', [MeetingController::class, 'update'])->name('update');
        Route::delete('/', [MeetingController::class, 'destroy'])->name('destroy');
        Route::post('/cancel', [MeetingController::class, 'cancel'])->name('cancel');

        Route::post('/participants', [MeetingController::class, 'invite'])->name('invite');
        Route::patch('/rsvp', [MeetingController::class, 'rsvp'])->name('rsvp');
        Route::post('/attendance', [MeetingController::class, 'attendance'])->name('attendance');
        Route::get('/join', [MeetingController::class, 'join'])->name('join');

        // Recordings
        Route::post('/recordings', [MeetingController::class, 'storeRecording'])
            ->middleware('throttle:uploads')
            ->name('recordings.store');
        Route::get('/recordings/{recording}/download', [MeetingController::class, 'downloadRecording'])->name('recordings.download');

        // Notes & Comments
        Route::get('/notes', [MeetingController::class, 'notes'])->name('notes.index');
        Route::post('/notes', [MeetingController::class, 'storeNote'])->name('notes.store');
        Route::get('/comments', [MeetingController::class, 'comments'])->name('comments.index');
        Route::post('/comments', [MeetingController::class, 'storeComment'])->name('comments.store');
        Route::patch('/comments/{comment}', [MeetingController::class, 'updateComment'])->name('comments.update');
        Route::delete('/comments/{comment}', [MeetingController::class, 'deleteComment'])->name('comments.destroy');

        // AI Transcription & Summary
        Route::post('/transcribe', [MeetingController::class, 'transcribe'])->name('transcribe');
        Route::get('/transcript', [MeetingController::class, 'transcript'])->name('transcript');
        Route::post('/summarize', [MeetingController::class, 'summarize'])->name('summarize');
        Route::get('/summary', [MeetingController::class, 'summary'])->name('summary');
    });
});
