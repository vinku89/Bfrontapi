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
class TradeHistoryEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $market_symbol;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($message,$market_symbol)
    {
        $this->message = $message;
        $this->market_symbol=$market_symbol;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        Log::info('trade history broadcast TradeHistoryChannel_'.$this->market_symbol);
        return new Channel('TradeHistoryChannel_'.$this->market_symbol);
    }
}
