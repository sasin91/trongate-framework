<?php

class Messenger
{
    /**
     * The registered event classes
     * 
     * @var array<Channel>
     */
    protected array $channels = [];

    public function __construct(
        public readonly Fibers            $fibers,
        public readonly Client_registry   $clients,
        public readonly Redis_connection  $publisher,
        public readonly Redis_connection  $subscriber
    ) {
        $this->discover_channels();
    }

    public function discover_channels(): void
    {
        $channel_files = glob(__DIR__ . '/channels/*.php');

        foreach ($channel_files as $channel_file) {
            $channel_class = basename($channel_file, '.php');
            
            require_once $channel_file;

            if (class_exists($channel_class) && is_subclass_of($channel_class, Channel::class)) {
                $channel = new $channel_class($this, $this->fibers);

                $channel->listen();

                $this->channels[$channel->name()] = $channel;
            }
        }
    }

    public function broadcast(string $channel, string $message): void {
        $frame = Frame::encode(
            message: json_encode([
                'channel' => $channel,
                'message' => json_decode($message)
            ]),
            opcode: Opcode::TEXT
        );
    
        foreach ($this->clients as $client => $id) {
            if (!is_resource($client->socket)) {
                continue;
            }

            $written = @fwrite($client->socket, $frame->payload);

            if ($written === false) {
                $this->clients->remove($client);
            }
        }
    }

    /**
     * Broadcast the total number of unique clients on the site
     * 
     * @return void
     */
    public function broadcast_num_online(): void {
        $this->broadcast('state', json_encode([
            'num_clients' => count($this->clients),
            'num_online' => count($this->clients->unique())
        ]));
    }

    /**
     * Notify connected clients and instances of an event.
     * 
     * @param int $stream_id 
     * @param string $status 
     * @return void 
     * @throws RuntimeException 
     */
    public function publish(string $channel, string $message): void {
        $this->get_publisher()->write("PUBLISH {$channel} '{$message}'\r\n");
    }

    /**
     * Get the redis connection for publishing events
     * 
     * @return Redis_connection 
     */
    public function get_publisher(): Redis_connection
    {
        return $this->publisher;
    }

    /**
     * Get the redis connection for subscribing to events
     * 
     * @return Redis_connection 
     */
    public function get_subscriber(): Redis_connection
    {
        return $this->subscriber;
    }
}
