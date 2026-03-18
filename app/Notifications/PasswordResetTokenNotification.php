<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetTokenNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly ?string $resetUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $broker = config('auth.defaults.passwords', 'users');
        $expireMinutes = (int) config("auth.passwords.$broker.expire", 60);

        $frontendResetUrl = $this->resetUrl;
        if ($frontendResetUrl === null || trim($frontendResetUrl) === '') {
            $frontendResetUrl = config('app.frontend_reset_password_url');
        }
        $email = rawurlencode((string) $notifiable->getEmailForPasswordReset());
        $token = rawurlencode($this->token);

        $message = (new MailMessage())
            ->subject('Recuperação de senha - Dinfy')
            ->greeting('Oi!')
            ->line('Recebemos uma solicitação para redefinir a senha da sua conta.')
            ->line('Use o token abaixo no app para definir uma nova senha:')
            ->line($this->token)
            ->line("Esse token expira em $expireMinutes minutos.")
            ->line('Se você não solicitou essa alteração, ignore este e-mail.');

        if (is_string($frontendResetUrl) && trim($frontendResetUrl) !== '') {
            $url = rtrim($frontendResetUrl, '/');
            $separator = str_contains($url, '?') ? '&' : '?';
            $message->action(
                'Redefinir senha',
                "{$url}{$separator}token={$token}&email={$email}"
            );
        }

        return $message;
    }
}
