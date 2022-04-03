<?php

namespace Amp\Http\Server;

use Amp\Future;
use Amp\Http\InvalidHeaderException;
use Amp\Http\Message;
use function Amp\async;

final class Trailers
{
    /** @see https://tools.ietf.org/html/rfc7230#section-4.1.2 */
    public const DISALLOWED_TRAILERS = [
        "authorization" => true,
        "content-encoding" => true,
        "content-length" => true,
        "content-range" => true,
        "content-type" => true,
        "cookie" => true,
        "expect" => true,
        "host" => true,
        "pragma" => true,
        "proxy-authenticate" => true,
        "proxy-authorization" => true,
        "range" => true,
        "te" => true,
        "trailer" => true,
        "transfer-encoding" => true,
        "www-authenticate" => true,
    ];

    /** @var string[] */
    private readonly array $fields;

    /** @var Future<Message> */
    private readonly Future $headers;

    /**
     * @param Future<string[]|string[][]> $future Resolved with the trailer values.
     * @param string[] $fields Expected header fields. May be empty, but if provided, the array of
     *     headers used to resolve the given promise must contain exactly the fields given in this array.
     *
     * @throws InvalidHeaderException If the fields list contains a disallowed field.
     */
    public function __construct(Future $future, array $fields = [])
    {
        $this->fields = $fields = \array_map('strtolower', $fields);

        foreach ($this->fields as $field) {
            if (isset(self::DISALLOWED_TRAILERS[$field])) {
                throw new InvalidHeaderException(\sprintf("Field '%s' is not allowed in trailers", $field));
            }
        }

        $this->headers = async(static function () use ($future, $fields): Message {
            return new class($future->await(), $fields) extends Message {
                public function __construct(array $headers, array $fields)
                {
                    $this->setHeaders($headers);

                    $keys = \array_keys($this->getHeaders());

                    if (!empty($fields)) {
                        // Note that the Trailer header does not need to be set for the message to include trailers.
                        // @see https://tools.ietf.org/html/rfc7230#section-4.4

                        if (\array_diff($fields, $keys)) {
                            throw new InvalidHeaderException("Trailers do not contain the expected fields");
                        }

                        return; // Check below unnecessary if fields list is set.
                    }

                    foreach ($keys as $field) {
                        if (isset(Trailers::DISALLOWED_TRAILERS[$field])) {
                            throw new InvalidHeaderException(\sprintf("Field '%s' is not allowed in trailers", $field));
                        }
                    }
                }
            };
        });

        // Future may fail due to client disconnect or error, but we don't want to force awaiting.
        $this->headers->ignore();
    }

    /**
     * @return string[] List of expected trailer fields. May be empty, but still receive trailers.
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function await(): Message
    {
        return $this->headers->await();
    }
}
