<?php
namespace byTorsten\PubSub\Socket;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use byTorsten\GraphQL\Subscriptions\PubSub\PubSubAsyncIterator;
use byTorsten\GraphQL\Subscriptions\Iterator\AsyncIteratorInterface;
use byTorsten\GraphQL\Subscriptions\PubSubInterface;
use function React\Promise\resolve;

class PubSub implements PubSubInterface
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var array
     */
    protected $subscriptions = [];

    /**
     * @var int
     */
    protected $subIdCounter = 0;

    /**
     * @param string $path
     */
    public function __construct(string $path = null)
    {
        $this->path = $path;
    }

    /**
     * @param string $path
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * @param string $triggerName
     * @param $payload
     * @return bool
     */
    public function publish(string $triggerName, $payload): bool
    {
        $this->server->write($triggerName, $payload);
        return true;
    }

    /**
     * @param string $triggerName
     * @param callable $onMessage
     * @param array $options
     * @return PromiseInterface
     */
    public function subscribe(string $triggerName, callable $onMessage, array $options = []): PromiseInterface
    {
        $this->server->on($triggerName, $onMessage);

        $this->subIdCounter += 1;
        $this->subscriptions[$this->subIdCounter] = [$triggerName, $onMessage];

        return resolve();
    }

    /**
     * @param int $subId
     */
    public function unsubscribe(int $subId): void
    {
        if (isset($this->subscriptions[$subId])) {
            [$triggerName, $onMessage] = $this->subscriptions[$subId];
            unset($this->subscriptions[$subId]);
            $this->server->removeListener($triggerName, $onMessage);
        }
    }

    /**
     * @param array $triggers
     * @return AsyncIteratorInterface
     */
    public function asyncIterator(array $triggers): AsyncIteratorInterface
    {
        return new PubSubAsyncIterator($this, $triggers);
    }

    /**
     * @param LoopInterface $loop
     * @return PromiseInterface
     * @throws \Exception
     */
    public function setup(LoopInterface $loop): PromiseInterface
    {
        $this->server = new Server($loop, $this->path);
        return resolve();
    }
}
