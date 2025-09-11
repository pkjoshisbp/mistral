<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpRegistrationNotification extends Notification
{
    use Queueable;

    protected $otp;

    /**
     * Create a new notification instance.
     */
    public function __construct($otp)
    {
        $this->otp = $otp;
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
                    ->subject('Email Verification Code - AI Chat Support')
                    ->line('Thank you for registering with AI Chat Support!')
                    ->line('Your email verification code is: **' . $this->otp . '**')
                    ->line('Please enter this 6-digit code on the registration page to complete your account setup.')
                    ->line('This code will expire in 10 minutes for security reasons.')
                    ->line('If you did not request this code, please ignore this email.')
                    ->line('Welcome to AI Chat Support!');
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
            'type' => 'registration_otp'
        ];
    }
}
