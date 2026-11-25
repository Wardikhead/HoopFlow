<?php

/** Application class Reset password notification. */

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    /**
     * Initialize the instance.
     *
     * @param  string  $token  The token.
     */
    public function __construct(private readonly string $token) {}

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
        $expires = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60,
        );

        return (new MailMessage)
            ->subject('Reset your HoopFlow password')
            ->greeting('Reset your password')
            ->line('We received a request to reset the password for your HoopFlow account.')
            ->action('Reset password', route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]))
            ->line("This link expires in {$expires} minutes.")
            ->line('If you did not request this, you can safely ignore this email. Your password will not change.')
            ->salutation('— HoopFlow');
    }
}
