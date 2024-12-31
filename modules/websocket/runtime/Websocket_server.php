<?php

require_once __DIR__ . '/Websocket_frame_encoding.php';
require_once __DIR__ . '/Websocket_client_connection.php';
require_once __DIR__ . '/Pub_sub_messaging.php';
require_once __DIR__ . '/Events.php';

class Websocket_server {
    use Websocket_frame_encoding;
    use Websocket_client_connection;
    use Pub_sub_messaging;
    use Events;

    /**
     * The server socket used for handling incoming connections.
     *
     * @var resource
     */
    private $server_socket;

    /**
     * List of client sockets and the last pong time
     * Keyed by resource ID
     *
     * @TODO: This may get quite large, i may need to reduce memory footprint
     * @var array<int, ['socket' => resource, 'last_pong' => int]>
     */
    protected array $clients = [];

    /**
     * The queue of connection handlers.
     * They're processed in order of First in, First out
     *
     * @var SplQueue
     */
    private SplQueue $fibers;

    /**
     * Whether the program is running or not.
     *
     * @var bool $running False indicates the program is not running, True indicates the program is running.
     */
    public bool $running = false;
    
    public function __construct(
        string                    $host = '127.0.0.1',
        int                       $port = 8085,
        private readonly int      $timeout = 0,
        private readonly int      $pingTimeout = 10,
        protected readonly string $redis_host = '127.0.0.1',
        protected readonly int    $redis_port = 6379,
    ) {
        // Create a TCP socket
        $this->server_socket = stream_socket_server(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr
        );

        if (!$this->server_socket) {
            throw new RuntimeException('Unable to create socket: ' . $errstr);
        }

        pcntl_async_signals(true);

        $this->fibers = new SplQueue();
        
        $this->establish_publisher_connection();
        $this->establish_subscriber_connection();
        $this->subscribe_to_events();
    }

    public function listen(): void {
        $this->running = true;

        $timeout = $this->fibers->count() > 0 ? $this->timeout : null;

        while ($this->running) {
            $read = [$this->server_socket];
            
            $available_streams = $this->stream_select(
                read: $read,
                timeout: $timeout
            );

            pcntl_signal_dispatch();

            if ($available_streams === false) {
                // if a system call has been interrupted,
                // we cannot rely on it's outcome
                return;
            }

            $this->accept_client_connection();
            $this->flush();

            // prevent busy-loop
            usleep(100000); // Sleep for 100ms
        }
    }

    public function stream_select(array $read, ?array $write = null, ?array $except = null, ?int $timeout = null): int|false {
        /** @var ?callable $previous */
        $previous = set_error_handler(function ($errno, $errstr) use (&$previous) {
            // suppress warnings that occur when `stream_select()` is interrupted by a signal
            // PHP defines `EINTR` through `ext-sockets` or `ext-pcntl`, otherwise use common default (Linux & Mac)
            $eintr = \defined('SOCKET_EINTR') ? \SOCKET_EINTR : (\defined('PCNTL_EINTR') ? \PCNTL_EINTR : 4);
            if ($errno === \E_WARNING && str_contains($errstr, '[' . $eintr . ']: ')) {
                return false;
            }

            // forward any other error to registered error handler or print warning
            return ($previous !== null) ? \call_user_func_array($previous, \func_get_args()) : false;
        });

        try {
            $available_streams = stream_select(
                $read, 
                $write, 
                $except, 
                $timeout === null ? null : 0, $timeout
            );
            restore_error_handler();

            return $available_streams;
        } catch (\Throwable $e) {
            restore_error_handler();
            error_log($e->getMessage());
        }

        return false;
    }

    /**
     * Safely attempt to write the message
     * 
     * @param resource $fp 
     * @param string $data 
     * @return bool 
     */
    protected function fwrite($fp, string $data): bool {
        if (is_resource($fp) === false) {
            return false;
        }

        $totalBytes = strlen($data);
        $writtenBytes = 0;
        $maxRetries = 5;
        $retries = 0;
    
        while ($writtenBytes < $totalBytes && $retries < $maxRetries) {
            $result = @fwrite($fp, substr($data, $writtenBytes));
    
            if ($result === false) {
                error_log("Error writing [$data] to socket.");
                return false;
            } elseif ($result === 0) {
                error_log("Write returned 0 bytes; connection may be closed.");
                return false;
            }
    
            $writtenBytes += $result;
    
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

    /**
     * Process the queued fibers
     *
     * @return void
     */
    private function flush(): void {
        $count = $this->fibers->count();

        while ($count--) {
            $fiber = $this->fibers->dequeue();

            if ($fiber->isSuspended()) {
                $fiber->resume();
            }

            if (!$fiber->isTerminated()) {
                $this->fibers->enqueue($fiber);
            }
        }
    }
}
