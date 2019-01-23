<?php

declare(strict_types = 1);

use function Amp\Promise\wait;
use ServiceBus\Common\Messages\Message;
use function ServiceBus\Common\uuid;
use ServiceBus\MessageSerializer\MessageEncoder;
use ServiceBus\MessageSerializer\Symfony\SymfonyMessageSerializer;
use ServiceBus\Transport\Amqp\AmqpConnectionConfiguration;
use ServiceBus\Transport\Amqp\AmqpTransportLevelDestination;
use ServiceBus\Transport\PhpInnacle\PhpInnacleTransport;
use ServiceBus\Transport\Common\Package\OutboundPackage;
use ServiceBus\Transport\Common\Transport;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Tools message publisher
 *
 * For tests/debug only
 */
final class ToolsPublisher
{
    /**
     * @var Transport|null
     */
    private $transport;

    /**
     * @var MessageEncoder
     */
    private $encoder;

    /**
     * @param string $envPath
     *
     * @throws \Symfony\Component\Dotenv\Exception\FormatException
     * @throws \Symfony\Component\Dotenv\Exception\PathException
     */
    public function __construct(string $envPath)
    {
        (new Dotenv())->load($envPath);

        $this->encoder = new SymfonyMessageSerializer();
    }

    /**
     * Send message to queue
     *
     * @param Message     $message
     * @param string|null $traceId
     * @param string|null $topic
     * @param string|null $routingKey
     *
     * @return void
     *
     * @throws \ServiceBus\MessageSerializer\Exceptions\EncodeMessageFailed
     * @throws \ServiceBus\Transport\Common\Exceptions\InvalidConnectionParameters
     * @throws \ServiceBus\Transport\Common\Exceptions\SendMessageFailed
     * @throws \Throwable
     */
    public function sendMessage(Message $message, string $traceId = null, ?string $topic = null, ?string $routingKey = null): void
    {
        $topic      = (string) ($topic ?? \getenv('SENDER_DESTINATION_TOPIC'));
        $routingKey = (string) ($routingKey ?? \getenv('SENDER_DESTINATION_TOPIC_ROUTING_KEY'));

        /** @noinspection PhpUnhandledExceptionInspection */
        wait(
            $this->transport()->send(
                OutboundPackage::create(
                    $this->encoder->encode($message),
                    [Transport::SERVICE_BUS_TRACE_HEADER => $traceId ?? uuid()],
                    new AmqpTransportLevelDestination($topic, $routingKey),
                    uuid()
                )
            )
        );
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @return Transport
     *
     * @throws \ServiceBus\Transport\Common\Exceptions\InvalidConnectionParameters
     */
    private function transport(): Transport
    {
        if(null === $this->transport)
        {
            $this->transport = new PhpInnacleTransport(
                new AmqpConnectionConfiguration(\getenv('TRANSPORT_CONNECTION_DSN'))
            );

            /** @noinspection PhpUnhandledExceptionInspection */
            wait($this->transport->connect());
        }

        return $this->transport;
    }
}
