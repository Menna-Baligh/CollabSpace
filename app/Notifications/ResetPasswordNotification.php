<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly string $token,
        public readonly string $email
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('services.frontend.reset_password_url', 'http://localhost:3000/reset-password');
        $resetUrl = sprintf('%s?token=%s&email=%s', $frontendUrl, urlencode($this->token), urlencode($this->email));

        return (new MailMessage)
            ->subject(__('Reset Password Notification'))
            ->line(__('You are receiving this email because we received a password reset request for your account.'))
            ->action(__('Reset Password'), $resetUrl)
            ->line(__('This password reset link will expire in :count minutes.', ['count' => config('auth.passwords.users.expire', 60)]))
            ->line(__('If you did not request a password reset, no further action is required.'));
    }
}
