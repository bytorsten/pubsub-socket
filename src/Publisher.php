<?php
namespace byTorsten\PubSub\Socket;

use Socket\Raw\Factory as SocketFactory;
use Socket\Raw\Exception as SocketException;
use Socket\Raw\Socket;

class Publisher
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var Socket
     */
    protected $client;

    /**
     * @param string $path
     */
    public function __construct(string $path = null)
    {
        $this->path = $path;
        register_shutdown_function([$this, 'shutdownClient']);
    }

    /**
     * @param string $path
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * @return Socket
     */
    public function getClient(): Socket
    {
        if ($this->client === null) {
            $factory = new SocketFactory();
            $client = $factory->createClient('unix://' . $this->path);
            $client->setBlocking(false);
            $this->client = $client;
        }

        return $this->client;
    }

    /**
     * @param string $channelName
     * @param $payload
     */
    public function publish(string $channelName, $payload)
    {
        $data = [
            'channel' => $channelName,
            'payload' => $payload
        ];

        $encodedData = json_encode($data);
        $message = '<[[' . mb_strlen($encodedData) . ']]>' . $encodedData;


        $client = $this->getClient();

        // automatically retry on disconnect
        try {
            $client->write($message);
        } catch (SocketException $exception) {
            $this->shutdownClient();
            $client = $this->getClient();
            $client->write($message);
        }
    }

    /**
     *
     */
    public function shutdownClient()
    {
        if ($this->client) {
            $this->client->close();
            $this->client = null;
        }
    }
}
