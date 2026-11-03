<?php declare(strict_types=1);
namespace App\Notifications;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(User $notifiable): MailMessage
    {
        $url = url("/api/v1/auth/email/verify/{$notifiable->id}/{$notifiable->email_verification_token}");
        return (new MailMessage)->subject('Verify Your Email Address')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('Click the button below to verify your email address.')
            ->action('Verify Email Address', $url)
            ->line('This link expires in 24 hours.')
            ->line('If you did not create an account, no action is required.');
    }
}
