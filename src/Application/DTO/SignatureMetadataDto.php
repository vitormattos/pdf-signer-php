<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final class SignatureMetadataDto
{
    public function __construct(
        public readonly ?string $reason = null,
        public readonly ?string $location = null,
        public readonly ?SignatureActorDto $actor = null,
    ) {}
}
