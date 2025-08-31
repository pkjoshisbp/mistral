<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Subscription;

class SubscriptionRenewal extends Notification implements ShouldQueue
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
                    ->subject('AI Chat Support - Subscription Renewed Successfully!')
                    ->line('Great news! Your AI Chat Support subscription has been renewed successfully.')
                    ->line('Plan: ' . $this->subscription->plan_name)
                    ->line('Amount: $' . number_format($this->subscription->amount / 100, 2) . ' ' . strtoupper($this->subscription->currency))
                    ->line('Next Billing Date: ' . $this->subscription->current_period_end->format('F j, Y'))
                    ->line('Your service will continue without interruption.')
                    ->action('View Dashboard', url('/dashboard'))
                    ->line('Thank you for your continued trust in AI Chat Support!');
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
            'amount' => $this->subscription->amount,
            'currency' => $this->subscription->currency,
            'renewed_at' => $this->subscription->updated_at,
        ];
    }
}