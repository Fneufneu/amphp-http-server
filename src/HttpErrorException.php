<?php declare(strict_types=1);

namespace Amp\Http\Server;

final class HttpErrorException extends \Exception
{
    public function __construct(private readonly int $status, private readonly ?string $reason = null)
    {
        parent::__construct('Error ' . $status . ($this->reason !== null && $this->reason !== '' ? ': ' . $reason : ''));
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}