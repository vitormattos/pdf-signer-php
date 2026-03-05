<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

final class PageDescriptor
{
    /**
     * @param  array<int, mixed>  $size
     */
    public function __construct(
        public readonly int $id,
        public readonly array $size,
    ) {}
}
