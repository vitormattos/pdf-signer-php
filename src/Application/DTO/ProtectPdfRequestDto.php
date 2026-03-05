<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final class ProtectPdfRequestDto
{
    public function __construct(
        public readonly PdfContentDto $pdf,
        public readonly ProtectionOptionsDto $options,
    ) {}
}
