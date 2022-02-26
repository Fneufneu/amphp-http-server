<?php

namespace Amp\Http\Server\Driver;

use Amp\Socket\Socket;
use Psr\Log\LoggerInterface;

final class ConnectionLimitingClientFactory implements ClientFactory
{
    private ClientFactory $delegate;

    private LoggerInterface $logger;

    private int $clientCount = 0;

    /** @var Client[] */
    private array $clientsPerIp = [];

    public function __construct(ClientFactory $delegate, LoggerInterface $logger)
    {
        $this->delegate = $delegate;
        $this->logger = $logger;
    }

    public function createClient(Socket $socket): ?Client
    {
        if (++$this->clientCount > $options->getConnectionLimit()) {
            $this->logger->warning("Client denied: too many existing connections");
            $socket->close();

            return null;
        }

        $ip = $net = $socket->getRemoteAddress()->getHost();
        $packedIp = @\inet_pton($ip);

        if ($packedIp !== false && isset($net[4])) {
            $net = \substr($net, 0, 7 /* /56 block for IPv6 */);
        }

        $this->clientsPerIp[$net] ??= 0;
        $clientsPerIp = ++$this->clientsPerIp[$net];

        $client = $this->delegate->createClient($socket);
        if ($client === null) {
            $this->clientCount--;

            return null;
        }

        $client->onClose(function (Client $client) use ($net): void {
            if (--$this->clientsPerIp[$net] === 0) {
                unset($this->clientsPerIp[$net]);
            }

            --$this->clientCount;
        });

        // Exclude all connections that are via unix sockets.
        if ($socket->getLocalAddress()->getPort() === null) {
            return $client;
        }

        // Connections on localhost are excluded from the connections per IP setting.
        // Checks IPv4 loopback (127.x), IPv6 loopback (::1) and IPv4-to-IPv6 mapped loopback.
        if ($ip === "::1" || \str_starts_with($ip, "127.") //
            || \str_starts_with(\inet_pton($ip), "\0\0\0\0\0\0\0\0\0\0\xff\xff\x7f")) {
            return $client;
        }

        if ($clientsPerIp >= $options->getConnectionsPerIpLimit()) {
            if (isset($packedIp[4])) {
                $ip .= "/56";
            }

            $this->logger->warning("Client denied: too many existing connections from {$ip}");
            $client->close();

            return null;
        }

        return $client;
    }
}
