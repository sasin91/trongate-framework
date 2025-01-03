<?php

final class Client implements ArrayAccess
{
    /**
     * The underlying PHP socket resource
     * 
     * @var resource
     */
    public $socket;


    /**
     * The resource ID
     * 
     * @var int
     */
    public int $id;

    /**
     * Timestamp of the last received pong
     * 
     * @var int
     */
    public int $last_pong;

    /**
     * Timestamp of the last sent ping
     * 
     * @var int
     */
    public int $last_ping;


    public ?string $trongateToken = null;
    public ?int $user_id = null;
    public ?string $fingerprint = null;

    public Runtime $runtime;

    public int $maxWebsocketFrameSize = 1_048_576; // 1MB, default max for chrome & node.js


    public function __construct(
        $socket,
        Runtime $runtime
    ) {
        $this->socket = $socket;
        $this->id = (int)$socket;
        $this->last_pong = time();
        $this->last_ping = time();

        $this->runtime = $runtime;
    }

    public function __destruct()
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->runtime->messenger->broadcast_num_online();
    }

    public function initialization_fiber(): Fiber {
        $fiber = new Fiber(function () {
            $header_string = '';

            // Non-blocking socket, loop until headers are read
            while (!feof($this->socket)) {
                $data = fread($this->socket, 1024);
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
                require_once __DIR__ . '/communication/Handshake.php';
                
                $handshake = new Handshake($header_string);

                $this->reply($handshake);
            }

            // parse query params
            preg_match('/GET\s.*\?(.*)\sHTTP/', $header_string, $matches);
            parse_str($matches[1], $queryParams);

            $fingerprint = $queryParams['fingerprint'];
            $token = $queryParams['trongateToken'] ?? null;
            $userId = isset($queryParams['user_id']) ? (int)$queryParams['user_id'] : null;

            $this->fingerprint = $fingerprint;
            $this->trongateToken = $token;
            $this->user_id = $userId;

            $this->runtime->messenger->broadcast_num_online();

            // Client initiated,
            // terminate fiber
            return;
        });

        $fiber->start();

        return $fiber;
    }

    /**
     * Listen for websocket frames from a client and process them
     *
     * @return Fiber
     * @throws Throwable
     */
    public function listener_fiber(): Fiber {
        $fiber = new Fiber(function () {
            while (is_resource($this->socket) && !feof($this->socket)) {
                $read = [$this->socket];
                $write = $except = null;
                $available_streams = stream_select($read, $write, $except, 0, 200000);
                
                if ($available_streams) {
                    $data = fread($this->socket, 1024);
                    $frame = Frame::decode($data);

                    if ($frame === null) {
                        continue;
                    }

                    if ($frame->opcode === Opcode::PONG) {
                        $this->last_pong = time();
                        continue;
                    }

                    if (!empty($frame->payload)) {
                        $decodedMessage = $frame->payload;
                        $json = @json_decode($decodedMessage, true);

                        if (is_array($json) && isset($json['handler'])) {
                            require_once __DIR__ . '/interop/Invoker.php';
                            $invoke = new Invoker();
                            $result = $invoke($json);

                            $this->reply($result->frame());
                        }
                    }
                }

                // Yield back control after each iteration
                Fiber::suspend();
            }
        });

        $fiber->start();
        
        return $fiber;
    }


    /**
     * keep the client alive using ping / pong packets
     *
     * @return Fiber
     * @throws Throwable If there is an error during execution
     */
    public function ping_fiber(): Fiber {
        $fiber = new Fiber(function () {
            // @see https://developer.mozilla.org/en-US/docs/Web/API/WebSockets_API/Writing_WebSocket_servers#pings_and_pongs_the_heartbeat_of_websockets
            $pingFrame = Frame::encode(
                message: '',
                opcode: Opcode::PING
            );

            while ($this->runtime->clients->has($this)) {
                $currentTime = time();
                $pong_diff = $currentTime - $this->last_pong;
                $ping_diff = $currentTime - $this->last_ping;

                if (
                    $pong_diff >= $this->runtime->pingTimeout 
                    // Prevent spamming pings
                    && $ping_diff > ($this->runtime->pingTimeout / 2)
                ) {
                    if (!is_resource($this->socket)) {
                        $this->offline();
                        // Terminate fiber
                        return;
                    }

                    $ping = @fwrite(
                        $this->socket, 
                        $pingFrame
                    );

                    if ($ping === false) {
                        $this->offline();
                        // Terminate fiber
                        return;
                    }

                    $this->last_ping = time();
                }

                Fiber::suspend();
            }
        });

        $fiber->start();

        return $fiber;
    }

    /**
     * Removes the client from the list of active clients and notifies listening clients.
     *
     * @return void
     */
    protected function offline(): void {
        $this->runtime->clients->remove($this);

        // avoid duplicate broadcasts: 
        // Defer `broadast_num_online()` to `__destruct` which follows removing the client
        // $this->runtime->messenger->broadcast_num_online();
    }

    public function reply(string $data): bool {
        if (is_resource($this->socket) === false) {
            return false;
        }
    
        $totalBytes = strlen($data);
        $maxWebsocketFrameSize = $this->maxWebsocketFrameSize;
        $payloadLength = strlen($data);
        if ($payloadLength > 125) {
            // If the payload length is greater than 125, we need additional bytes for the header.
            $maxWebsocketFrameSize -= 2; // The first two bytes are usually the FIN bit & MASK bit followed by the payload.
        }
    
        if ($totalBytes > $maxWebsocketFrameSize) {
            return $this->stream($data);
        }
    
        $writtenBytes = fwrite($this->socket, $data);
    
        return is_int($writtenBytes) && $writtenBytes > 0;
    }
    
    public function stream(string $data): bool {
        if (is_resource($this->socket) === false) {
            return false;
        }
    
        $totalBytes = strlen($data);
        $writtenBytes = 0;
        $maxRetries = 5;
        $retries = 0;
        $chunkSize = $this->maxWebsocketFrameSize - 2; // Subtract the assumed FIN & MASK bit
    
        while ($writtenBytes < $totalBytes && $retries < $maxRetries) {
            // Use the continuation opcode for subsequent frames
            $opcode = ($writtenBytes === 0) ? Opcode::TEXT : Opcode::CONTINUATION;
            
            $chunk = substr($data, $writtenBytes, $chunkSize);
            $frame = Frame::encode($chunk, $opcode);
    
            $result = @fwrite($this->socket, $frame);
    
            if ($result === false) {
                error_log("Error writing chunk to socket.");
                return false;
            } elseif ($result === 0) {
                error_log("Write returned 0 bytes; connection may be closed.");
                return false;
            }
    
            $writtenBytes += strlen($chunk);
    
            if ($writtenBytes < $totalBytes) {
                $retries++;
                usleep(100000);
            }
        }
    
        if ($writtenBytes < $totalBytes) {
            error_log("Failed to write all bytes after retries.");
            return false;
        }
    
        return true;
    }

    public function offsetExists(mixed $offset): bool {
        return isset($this->$offset);
    }

    public function offsetGet(mixed $offset): mixed {
        return $this->$offset ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void {
        if (property_exists($this, $offset) === false) {
            throw new InvalidArgumentException("Cannot set dynamic properties on Client.");
        }

        $this->$offset = $value;
    }

    public function offsetUnset(mixed $offset): void {
        unset($this->$offset);
    }
}
