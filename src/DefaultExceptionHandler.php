<?php declare(strict_types=1);

namespace Amp\Http\Server;

use Amp\Http\HttpStatus;
use Psr\Log\LoggerInterface as PsrLogger;

/**
 * Simple exception handler that writes a message to the logger and returns an error page generated by the provided
 * {@see ErrorHandler}.
 */
final class DefaultExceptionHandler implements ExceptionHandler
{
    public function __construct(
        private readonly ErrorHandler $errorHandler,
        private readonly PsrLogger $logger,
    ) {
    }

    public function handleException(Request $request, \Throwable $exception): Response
    {
        $client = $request->getClient();
        $method = $request->getMethod();
        $uri = (string) $request->getUri();
        $protocolVersion = $request->getProtocolVersion();
        $local = $client->getLocalAddress()->toString();
        $remote = $client->getRemoteAddress()->toString();

        $this->logger->error(
            \sprintf(
                "Unexpected %s with message '%s' thrown from %s:%d when handling request: %s %s HTTP/%s %s on %s",
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $method,
                $uri,
                $protocolVersion,
                $remote,
                $local,
            ),
            [
                'exception' => $exception,
                'method' => $method,
                'uri' => $uri,
                'protocolVersion' => $protocolVersion,
                'local' => $local,
                'remote' => $remote,
            ],
        );

        return $this->errorHandler->handleError(HttpStatus::INTERNAL_SERVER_ERROR, request: $request);
    }
}