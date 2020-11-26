<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Log;
class PairingDataEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $base_currency;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($message,$base_currency)
    {
        $this->message = $message;
        $this->base_currency =$base_currency;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        Log::info("pairing data evernt");
        $channel= new Channel('PairingData_'.$this->base_currency);
        Log::info("pairing data evernt2");
        return $channel;
    }
}
