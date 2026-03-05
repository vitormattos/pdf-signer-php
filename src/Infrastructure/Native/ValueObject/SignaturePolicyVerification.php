<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\ValueObject;

final class SignaturePolicyVerification
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $message = null,
    ) {}
}
