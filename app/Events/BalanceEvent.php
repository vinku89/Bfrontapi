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
class BalanceEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $message;
    public $user_id;
    public $market_symbol;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($message,$user_id,$market_symbol)
    {
        $this->message = $message;
        $this->user_id = $user_id;
        $this->market_symbol=$market_symbol;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        Log::info('Balance_'.$this->market_symbol."_".$this->user_id);
        return new PrivateChannel('Balance_'.$this->market_symbol."_".$this->user_id);
    }
}
