<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final class PdfContentDto
{
    public function __construct(public readonly string $content) {}
}
