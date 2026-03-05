<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final class SignatureActorDto
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $contactInfo = null,
    ) {}
}
