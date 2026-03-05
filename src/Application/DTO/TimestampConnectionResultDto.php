<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final class TimestampConnectionResultDto
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message = null,
        public readonly ?int $statusCode = null,
    ) {}
}
