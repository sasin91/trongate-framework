<?php

class Redis_connection
{
    /**
     * The underlying connection resource
     * 
     * @var resource
     */
    public $connection = null;

    public function __construct(
        protected readonly  string  $host = '127.0.0.1',
        protected readonly  int     $port = 6379,
        private readonly    bool    $lazy = true
    ) {
        if ($lazy === false) {
            $this->connect();
        }
    }

    public function connect(): void {
        $this->connection = stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr);

        if (!$this->connection) {
            throw new RuntimeException("Could not connect to Redis: $errstr ($errno)");
        }

        stream_set_blocking($this->connection, false);
    }

    /**
     * Write the given command to the underlying connection
     * 
     * @param string $command 
     * @return int the amount of bytes written 
     * @throws RuntimeException 
     */
    public function write(string $command): int {
        if (!is_resource($this->connection) || feof($this->connection)) {
            $this->connect();
        }

        $published = fwrite($this->connection, $command);

        if ($published === false) {
            error_log("Failed to publish message");
        } elseif ($published < strlen($command)) {
            error_log("Partial write detected");
        }

        return $published;
    }
}
