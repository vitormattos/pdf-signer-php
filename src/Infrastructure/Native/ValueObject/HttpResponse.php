<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\ValueObject;

final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        public readonly ?string $transportError = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
