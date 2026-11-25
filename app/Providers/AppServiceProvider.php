<?php

namespace App\Providers;

use App\Contracts\AiSummarizerServiceInterface;
use App\Contracts\TranscriptionServiceInterface;
use App\Models\AiConversation;
use App\Models\ChatThreadReply;
use App\Policies\AiConversationPolicy;
use App\Policies\ChatThreadPolicy;
use App\Services\GeminiSummarizerService;
use App\Services\GroqTranscriptionService;
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
        $this->app->bind(TranscriptionServiceInterface::class, GroqTranscriptionService::class);
        $this->app->bind(AiSummarizerServiceInterface::class, GeminiSummarizerService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Gate::policy(ChatThreadReply::class, ChatThreadPolicy::class);
        Gate::policy(AiConversation::class, AiConversationPolicy::class);

        RateLimiter::for('ai-chat', function (Request $request) {
            return Limit::perMinute(config('services.rate_limits.ai_chat', 30))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('ai-attachments', function (Request $request) {
            return Limit::perMinute(config('services.rate_limits.ai_attachments', 10))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('ai-regenerate', function (Request $request) {
            return Limit::perMinute(config('services.rate_limits.ai_regenerate', 10))
                ->by($request->user()?->id ?: $request->ip());
        });

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
