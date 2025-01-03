<?php


/**
 * @template TKey of Client
 * @template TValue of int
 * @template-implements IteratorAggregate<TKey, TValue>
 */
class Client_registry implements Countable, IteratorAggregate
{
    /**
     * List of client sockets and the last pong time
     * Keyed by resource ID
     *
     * @var WeakMap<Client, int>
     */
    public readonly WeakMap $map;

    public function __construct()
    {
        $this->map = new WeakMap();
    }

    public function getIterator(): Traversable {
        return $this->map;
    }

    public function count(): int {
        return count($this->map);
    }

    public function unique(): array {
        $unique_clients = [];

        foreach($this->map as $client => $id) {
            if (isset($unique_clients[$client->fingerprint])) {
                continue;
            }

            $unique_clients[$client->fingerprint] = $client;
        }

        return $unique_clients;
    }

    public function add(Client $client): void {
        $client_id = (int)$client->socket;

        $this->map[$client] = $client_id;
    }

    public function has(Client $client): bool {
        return isset($this->map[$client]);
    }

    public function remove(Client $client): void {
        unset($this->map[$client]);
    }
}
