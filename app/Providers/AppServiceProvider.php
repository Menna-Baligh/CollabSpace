<?php

namespace App\Providers;

use App\Contracts\AiSummarizerServiceInterface;
use App\Contracts\TranscriptionServiceInterface;
use App\Models\ChatThreadReply;
use App\Policies\ChatThreadPolicy;
use App\Services\GeminiSummarizerService;
use App\Services\WhisperTranscriptionService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TranscriptionServiceInterface::class, WhisperTranscriptionService::class);
        $this->app->bind(AiSummarizerServiceInterface::class, GeminiSummarizerService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Gate::policy(ChatThreadReply::class, ChatThreadPolicy::class);

        RateLimiter::for('chat-messages', function (Request $request) {
            return Limit::perMinute(config('services.rate_limits.chat_messages', 60))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(config('services.rate_limits.uploads', 20))
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
