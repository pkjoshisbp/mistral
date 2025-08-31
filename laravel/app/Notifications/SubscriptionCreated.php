<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Subscription;

class SubscriptionCreated extends Notification implements ShouldQueue
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
                    ->subject('Welcome to AI Chat Support - Subscription Activated!')
                    ->line('Congratulations! Your AI Chat Support subscription has been successfully activated.')
                    ->line('Plan: ' . $this->subscription->plan_name)
                    ->line('Price: $' . number_format($this->subscription->amount / 100, 2) . ' ' . strtoupper($this->subscription->currency))
                    ->line('Billing Period: ' . ucfirst($this->subscription->billing_period))
                    ->line('Next Billing Date: ' . $this->subscription->current_period_end->format('F j, Y'))
                    ->action('Access Your Dashboard', url('/dashboard'))
                    ->line('Thank you for choosing AI Chat Support!');
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
        ];
    }
}
