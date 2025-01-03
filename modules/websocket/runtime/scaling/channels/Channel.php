<?php

abstract class Channel
{
    private ?string $_name = null;

    public function __construct(
        public readonly Messenger   $messenger,
        public readonly Fibers      $fibers
    ) {
        //
    }

    /**
     * By default, we infer the channel name by from the class name.
     * feel free to override this method in your event class.
     * 
     * @return string 
     */
    public function name(): string
    {
        if ($this->_name !== null) {
            return $this->_name;
        } 

        $class_name = str_replace('\\', '/', static::class);

        $class_name = strtolower($class_name);

        return $this->_name = basename($class_name);
    }

    public function publish(array $payload)
    {
        $this->messenger->publish(
            $this->name(),
            json_encode($payload)
        );
    }

    public function listen()
    {
        $fiber = new Fiber($this->json_subscription(
            $this->name(),
            $this->messenger->broadcast(...)
        ));

        $fiber->start(
            $this->messenger->get_subscriber()
        );

        $this->fibers->enqueue($fiber);
    }

    /**
     * Creates a subscription to a given channel and listens for incoming JSON messages.
     *
     * @param string $channel The channel name to subscribe to.
     * @param callable $on_message
     * @return Closure A closure that handles the subscription process and listens for messages on the given channel.
     */
    public function json_subscription(string $channel, callable $on_message): Closure
    {
        return function ($socket) use ($on_message, $channel) {
            $subscribed = $this->messenger->get_subscriber()->write("SUBSCRIBE $channel \r\n");
            if ($subscribed === false) {
                error_log("Error subscribing to $channel");
                return;
            }

            while (is_resource($socket) && !feof($socket)) {
                $read = [$socket];
                $write = $except = null;
                $available_streams = stream_select($read, $write, $except, 0, 200000);

                if ($available_streams !== false) {
                    $line = fread($socket, 1024);

                    if (is_string($line) && preg_match('/\{.*?}/', $line, $matches)) {
                        $parsed = $this->parse_redis_subscribe_response($line);

                        if ($parsed === false) {
                            break;
                        }

                        [$channel, $payload] = $parsed;
                        if ($matches[0] !== $payload) {
                            echo "Payload mismatch: $matches[0] !== $payload\n";
                        }
                        $on_message($channel, $payload);
                    }
                }

                Fiber::suspend();
            }
        };
    }

    /**
     * Parses a Redis subscribe response and returns the channel and payload.
     *
     * @param string $response The raw response from the Redis server.
     * @return array|false An array containing the channel and payload, or false if the response is invalid.
     **/
    protected function parse_redis_subscribe_response(string $response): array|false
    {
        $lines = explode("\r\n", $response);

        // Redis subscribe responses follow this structure:
        // [0] => *3 (number of elements in the response)
        // [1] => $7 (message type)
        // [2] => message (keyword for a subscribed message)
        // [3] => $12 (length of the channel name)
        // [4] => live_streams (the channel name)
        // [5] => $24 (length of the payload)
        // [6] => {"status":"new","id":17} (the actual payload)

        if (count($lines) < 7 || $lines[0] !== '*3') {
            return false;
        }

        $channel = $lines[4];
        $payload = $lines[6];

        return [
            $channel,
            $payload,
        ];
    }
}
