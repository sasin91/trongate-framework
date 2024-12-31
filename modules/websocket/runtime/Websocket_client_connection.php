<?php

trait Websocket_client_connection
{
    /**
     * The instantiated request handlers
     * 
     * @var array
     */
    protected array $handler_registry = [];

    
    /**
     * Accepts new client connections and initializes the client session.
     *
     * @return void
     *
     * @throws Throwable if an error occurs during client acceptance or initialization
     */
    public function accept_client_connection(): void {
        $client = @stream_socket_accept($this->server_socket, $this->timeout);

        if ($client) {
            $client_id = (int)$client;

            $this->clients[$client_id] = [
                'socket' => $client,
                'last_pong' => time(),
                'last_ping' => time(),
                'trongateToken' => null,
                'user_id' => null,
                'fingerprint' => null
            ];

            stream_set_blocking($client, false);
            $this->initiate_client_connection($client, $client_id);
            $this->listen_for_websocket_frames($client, $client_id);
            $this->keep_alive($client_id);
        }
    }

    /**
     * Initiates the client connection by reading the headers and sending the appropriate handshake
     * 
     * @param mixed $client 
     * @param int $client_id 
     * @return void 
     * @throws FiberError 
     * @throws Throwable 
     */
    protected function initiate_client_connection($client, int $client_id): void {
        $fiber = new Fiber(function ($client, $client_id) {
            $header_string = '';

            // Non-blocking socket, loop until headers are read
            while (!feof($client)) {
                $data = fread($client, 1024);
                if ($data === false) {
                    // No data, suspend the fiber and wait for more
                    Fiber::suspend();
                }
                $header_string .= $data;

                if (str_contains($header_string, "\r\n\r\n")) {
                    break;
                }
            }

            if (str_contains($header_string, "Upgrade: websocket")) {
                $this->send_websocket_handshake($client, $header_string);
            }

            // parse query params
            preg_match('/GET\s.*\?(.*)\sHTTP/', $header_string, $matches);
            parse_str($matches[1], $queryParams);

            $fingerprint = $queryParams['fingerprint'];
            $token = $queryParams['trongateToken'] ?? null;
            $userId = isset($queryParams['user_id']) ? (int)$queryParams['user_id'] : null;

            $this->emit_user_online($client_id, $fingerprint, $token, $userId);

            // Client initiated,
            // terminate fiber
            return;
        });

        $fiber->start($client, $client_id);
        $this->fibers->enqueue($fiber);
    }

        /**
     * Listen for websocket frames from a client and process them
     *
     * @param resource $client The client socket
     * @param int $client_id The client ID
     * @return void
     * @throws Throwable
     */
    protected function listen_for_websocket_frames($client, int $client_id): void {
        $fiber = new Fiber(function ($client, $client_id) {
            while (is_resource($client) && !feof($client)) {
                $read = [$client];
                $write = $except = null;
                $available_streams = stream_select($read, $write, $except, 0, 200000);
                
                if ($available_streams) {
                    $frame = fread($client, 1024);
                    $decoded = $this->decode_websocket_frame($frame);

                    if (!empty($decoded)) {
                        if (isset($decoded['type']) && $decoded['type'] === 'pong') {
                            $this->clients[$client_id]['last_pong'] = time();
                            continue;
                        }

                        $decodedMessage = $decoded['payload'];
                        $json = @json_decode($decodedMessage, true);
                        $response = $this->process_websocket_request($json ?? [], $client_id);
                        $responseFrame = $this->encode_websocket_frame($response);
                        $this->fwrite($client, $responseFrame);
                    }
                }

                // Yield back control after each iteration
                Fiber::suspend();
            }
        });

        $fiber->start($client, $client_id);
        $this->fibers->enqueue($fiber);
    }

    /**
     * Process the incoming websocket request
     * 
     * @param array $json The JSON decoded request
     * @param int $client_id The ID of the client
     * @return string The response to the request
     */
    protected function process_websocket_request(array $json, int $client_id): string {
        if (isset($json['handler']) === false) {
            return 'No handler specified';
        }
        
        $handler_directory = __DIR__ . '/handlers/';
        $handler = ucwords($json['handler']);
        
        if (file_exists($handler_directory . $handler . '.php') === false) {
            return "Handler not found: {$handler}";
        }
        
        if (isset($this->handler_registry[$handler])) {
            $handler_instance = $this->handler_registry[$handler];
        } else {
            require_once $handler_directory . $handler . '.php';
            $handler_instance = new $handler();
            $this->handler_registry[$handler] = $handler_instance;
        }
        
        $handler_method = $json['handler_method'] ?? 'handle';
        return $handler_instance->$handler_method($json, $this->clients[$client_id]);
    }

    /**
     * keep the client alive using ping / pong packets
     *
     * @param int $client_id The ID of the client to keep alive
     * @return void
     * @throws Throwable If there is an error during execution
     */
    protected function keep_alive(int $client_id): void {
        $fiber = new Fiber(function () use ($client_id) {
            // @see https://developer.mozilla.org/en-US/docs/Web/API/WebSockets_API/Writing_WebSocket_servers#pings_and_pongs_the_heartbeat_of_websockets
            $pingFrame = $this->encode_websocket_frame('', 0x9);

            while (isset($this->clients[$client_id])) {
                $client = $this->clients[$client_id];
                $currentTime = time();
                $pong_diff = $currentTime - $client['last_pong'];
                $ping_diff = $currentTime - $client['last_ping'];

                if (
                    $pong_diff >= $this->pingTimeout 
                    // Prevent spamming pings
                    && $ping_diff > ($this->pingTimeout / 2)
                ) {
                    if (!is_resource($client['socket'])) {
                        $this->emit_user_offline($client);
                        // Terminate fiber
                        return;
                    }

                    // @see https://developer.mozilla.org/en-US/docs/Web/API/WebSockets_API/Writing_WebSocket_servers#pings_and_pongs_the_heartbeat_of_websockets
                    $pingFrame = $this->encode_websocket_frame('', 0x9);
                    $ping = @fwrite(
                        $client['socket'], 
                        $pingFrame
                    );

                    if ($ping === false) {
                        $this->emit_user_offline($client);
                        // Terminate fiber
                        return;
                    }

                    $this->clients[$client_id]['last_ping'] = time();
                }

                Fiber::suspend();
            }
        });

        $fiber->start();
        $this->fibers->enqueue($fiber);
    }
}
