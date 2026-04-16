<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\CarbonImmutable;

class PasswordResetTokenNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly ?string $resetUrl = null,
        private readonly ?\DateTimeInterface $expiresAt = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fallbackExpireMinutes = (int) config('auth.passwords.users.expire', 60);
        $expireMinutes = $fallbackExpireMinutes;

        if ($this->expiresAt) {
            $expiresAt = CarbonImmutable::instance($this->expiresAt);
            $expireMinutes = max(
                1,
                (int) ceil(max(0, now()->diffInSeconds($expiresAt, false)) / 60)
            );
        }

        $frontendResetUrl = $this->resetUrl;
        if ($frontendResetUrl === null || trim($frontendResetUrl) === '') {
            $frontendResetUrl = config('app.frontend_reset_password_url');
        }
        $email = rawurlencode((string) $notifiable->getEmailForPasswordReset());
        $token = rawurlencode($this->token);
        $resetUrl = null;
        $deepLinkUrl = "dinfy://reset-password?token={$token}&email={$email}";
        if (is_string($frontendResetUrl) && trim($frontendResetUrl) !== '') {
            $url = rtrim($frontendResetUrl, '/');
            $separator = str_contains($url, '?') ? '&' : '?';
            $resetUrl = "{$url}{$separator}token={$token}&email={$email}";
        }

        return (new MailMessage())
            ->subject('Recuperação de senha - Dinfy')
            ->view('emails.password_reset', [
                'resetUrl' => $resetUrl,
                'deepLinkUrl' => $deepLinkUrl,
                'token' => $this->token,
                'expireMinutes' => $expireMinutes,
            ])
            ->text('emails.password_reset_text', [
                'resetUrl' => $resetUrl,
                'deepLinkUrl' => $deepLinkUrl,
                'token' => $this->token,
                'expireMinutes' => $expireMinutes,
            ]);
    }
}
