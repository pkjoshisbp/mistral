<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Subscription;

class SubscriptionExpired extends Notification implements ShouldQueue
{
    use Queueable;

    public $subscription;

    /**
     * Create a new notification instance.
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
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
                    ->subject('AI Chat Support - Subscription Expired')
                    ->line('We wanted to let you know that your AI Chat Support subscription has expired.')
                    ->line('Plan: ' . $this->subscription->plan_name)
                    ->line('Expired on: ' . $this->subscription->current_period_end->format('F j, Y'))
                    ->line('To continue using our AI Chat Support service, please renew your subscription.')
                    ->action('Renew Subscription', url('/pricing'))
                    ->line('If you have any questions, please don\'t hesitate to contact our support team.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan_name,
            'expired_at' => $this->subscription->current_period_end,
        ];
    }
}
