<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpLoginNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private string $otp;
    private int $expiryMinutes;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $otp, int $expiryMinutes = 10)
    {
        $this->otp = $otp;
        $this->expiryMinutes = $expiryMinutes;
    }

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
        return (new MailMessage)
            ->subject('Your AI Chat Support Login Code')
            ->greeting('Hello!')
            ->line('You requested to log in to your AI Chat Support account.')
            ->line("Your verification code is: **{$this->otp}**")
            ->line("This code will expire in {$this->expiryMinutes} minutes.")
            ->line('If you did not request this code, please ignore this email.')
            ->action('Login to Dashboard', url('/login'))
            ->line('Thank you for using AI Chat Support!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'otp' => $this->otp,
            'expires_in_minutes' => $this->expiryMinutes
        ];
    }
}
