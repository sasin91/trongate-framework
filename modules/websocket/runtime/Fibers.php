<?php

final readonly class Fibers implements Countable
{
    /**
     * The queue of connection handlers.
     * They're processed in order of First in, First out
     *
     * @var SplQueue
     */
    private SplQueue $queue;

    public function __construct() {
        $this->queue = new SplQueue();
    }

    /**
     * Get the number of queued fibers
     * 
     * @return int 
     */
    public function count(): int {
        return $this->queue->count();
    }

    /**
     * Add a given fiber to the runtime queue
     * This fiber will repeatedly be called until it returns.
     * 
     * @param Fiber $fiber 
     * @return void 
     */
    public function enqueue(Fiber $fiber): void {
        $this->queue->enqueue($fiber);
    }

    /**
     * Get the oldest of the added fibers
     * 
     * @return Fiber 
     */
    public function dequeue(): Fiber {
        return $this->queue->dequeue();
    }
}
