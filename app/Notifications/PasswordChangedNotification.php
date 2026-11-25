<?php

/** Application class Password changed notification. */

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * To mail.
     *
     * @param  User  $notifiable  The notifiable.
     * @return MailMessage The result.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your HoopFlow password was changed')
            ->greeting('Your password was changed')
            ->line('The password for your HoopFlow account was just reset.')
            ->line('All other sessions were signed out for your security.')
            ->line('If you did not make this change, reset your password immediately and contact support.')
            ->action('Sign in to HoopFlow', route('login'))
            ->salutation('— HoopFlow');
    }
}
