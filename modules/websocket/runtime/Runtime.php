<?php

class Runtime {
    /**
     * The server socket used for handling incoming connections.
     *
     * @var resource
     */
    private $server_socket;

    /**
     * Whether the program is running or not.
     *
     * @var bool $running False indicates the program is not running, True indicates the program is running.
     */
    public bool $running = false;
    
    public function __construct(
        public readonly Fibers           $fibers,
        public readonly Client_registry $clients,
        public readonly Messenger       $messenger,
        string                          $host = '127.0.0.1',
        int                             $port = 8085,
        public readonly int             $timeout = 0,
        public readonly int             $pingTimeout = 10,
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
     * Accepts new client connections and initializes the client session.
     *
     * @return void
     *
     * @throws Throwable if an error occurs during client acceptance or initialization
     */
    public function accept_client_connection(): void {
        $socket = @stream_socket_accept($this->server_socket, $this->timeout);

        if ($socket) {
            require_once __DIR__ . '/Client.php';

            $client = new Client(
                $socket, 
                $this
            );

            $this->clients->add($client);

            $this->fibers->enqueue($client->initialization_fiber());
            $this->fibers->enqueue($client->listener_fiber());
            $this->fibers->enqueue($client->ping_fiber());

            stream_set_blocking($socket, false);
        }
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
