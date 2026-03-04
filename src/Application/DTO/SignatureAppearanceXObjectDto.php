<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final readonly class SignatureAppearanceXObjectDto
{
    /**
     * @param  array<string, mixed>|null  $resources
     */
    public function __construct(
        public string $stream,
        public ?array $resources = null,
    ) {}
}
