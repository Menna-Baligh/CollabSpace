<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class JitsiService
{
    protected string $domain;

    protected ?string $appId;

    protected ?string $secret;

    public function __construct()
    {
        $this->domain = config('services.jitsi.domain', 'meet.jit.si');
        $this->appId = config('services.jitsi.app_id');
        $this->secret = config('services.jitsi.secret');
    }

    /**
     * Generate unique secure room name.
     */
    public function generateRoomName(string $prefix = 'collab'): string
    {
        return $prefix.'_'.Str::random(12).'_'.time();
    }

    /**
     * Build Jitsi web join URL for user.
     */
    public function buildJoinUrl(string $roomName, User $user, bool $isVideo = true): string
    {
        $room = urlencode($roomName);
        $displayName = urlencode($user->name);
        $email = urlencode($user->email);
        $avatar = $user->avatar_url ? urlencode($user->avatar_url) : '';

        $url = "https://{$this->domain}/{$room}#userInfo.displayName=\"{$displayName}\"&userInfo.email=\"{$email}\"";

        if ($avatar) {
            $url .= "&userInfo.avatarURL=\"{$avatar}\"";
        }

        if (! $isVideo) {
            $url .= '&config.startWithVideoMuted=true';
        }

        return $url;
    }
}
