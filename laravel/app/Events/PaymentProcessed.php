<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Subscription;

class PaymentProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $subscription;
    public $paymentData;

    /**
     * Create a new event instance.
     */
    public function __construct(Subscription $subscription, array $paymentData = [])
    {
        $this->subscription = $subscription;
        $this->paymentData = $paymentData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
