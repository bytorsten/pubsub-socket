<?php
namespace byTorsten\PubSub\Socket;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\UnixServer;

class Server extends EventEmitter
{
    /**
     * @var UnixServer
     */
    protected $server;

    /**
     * @var \SplObjectStorage
     */
    protected $connections;

    /**
     * Server constructor.
     * @param LoopInterface $loop
     * @param string $path
     */
    public function __construct(LoopInterface $loop, string $path)
    {
        $this->connections = new \SplObjectStorage();

        $directory = dirname($path);
        @mkdir($directory, 0777);
        @unlink($path);

        $this->server = new UnixServer($path, $loop);
        $this->server->on('connection', [$this, 'handleConnection']);
    }

    /**
     * @param ConnectionInterface $connection
     */
    public function handleConnection(ConnectionInterface $connection): void
    {
        $this->connections->attach($connection);

        $connection->on('close', function () use ($connection) {
            $this->connections->detach($connection);
        });

        $buffer = null;
        $expectedLength = -1;

        $handleData = function ($chunk) use (&$buffer, &$expectedLength, &$handleData, $connection) {
            if ($buffer === null) {
                preg_match('/<\[\[([0-9]+)]]>(.+)/', $chunk, $matches);

                if ($matches === null) {
                    $connection->close();
                    throw new \Exception('Malformed message: "' . $chunk . '"');
                }

                $expectedLength = $matches[1];
                $buffer = $matches[2];
            } else {
                $buffer .= $chunk;
            }

            if (mb_strlen($buffer) >= $expectedLength) {
                $message = mb_substr($buffer, 0, $expectedLength);
                $left = mb_substr($buffer, $expectedLength);

                $buffer = null;
                $expectedLength = -1;

                $decodedMessage = json_decode($message, true);

                if ($decodedMessage === null) {
                    $connection->close();
                    throw new \Exception('Message is not json decodable: "' . $message . '"');
                }

                if (!isset($decodedMessage['channel']) || !isset($decodedMessage['payload'])) {
                    throw new \Exception('Message does not follow protocol: "' . $message . '"');
                }

                $this->emit($decodedMessage['channel'], [$decodedMessage['payload']]);

                if (strlen($left) > 0) {
                    $handleData($left);
                }
            }
        };

        $connection->on('data',$handleData);
    }

    /**
     * @param string $channelName
     * @param $payload
     */
    public function write(string $channelName, $payload): void
    {
        $data = [
            'channel' => $channelName,
            'payload' => $payload
        ];

        $encodedData = json_encode($data);
        $message = '<[[' . mb_strlen($encodedData) . ']]>' . $encodedData;

        /** @var ConnectionInterface $connection */
        foreach ($this->connections as $connection) {
            $connection->write($message);
        }
    }
}
