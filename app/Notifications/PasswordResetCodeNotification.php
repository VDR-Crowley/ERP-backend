<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Seu código de redefinição de senha')
            ->greeting('Olá!')
            ->line('Use o código abaixo para redefinir sua senha no MiniERP:')
            ->line(new HtmlString("<h2 style=\"letter-spacing:4px\">{$this->code}</h2>"))
            ->line('O código expira em 15 minutos.')
            ->line('Se você não pediu essa redefinição, ignore este e-mail.');
    }
}
