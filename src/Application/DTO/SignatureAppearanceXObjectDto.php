<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final class SignatureAppearanceXObjectDto
{
    /**
     * @param  string  $stream     PDF content stream operators for the n2 layer
     * @param  array<string, mixed>|null  $resources  Resource dictionary (fonts, XObjects, etc.)
     */
    public function __construct(
        public readonly string $stream,
        public readonly ?array $resources = null,
    ) {}
}
