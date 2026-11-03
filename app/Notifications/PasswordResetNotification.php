<?php declare(strict_types=1);
namespace App\Notifications;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(private readonly string $token) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(User $notifiable): MailMessage
    {
        $url = url('/reset-password?'.http_build_query(['token'=>$this->token,'email'=>$notifiable->email]));
        return (new MailMessage)->subject('Password Reset Request')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('We received a request to reset your password.')
            ->action('Reset Password', $url)
            ->line('This link expires in **60 minutes** and can only be used once.')
            ->line('If you did not request this, please secure your account immediately.');
    }
}
