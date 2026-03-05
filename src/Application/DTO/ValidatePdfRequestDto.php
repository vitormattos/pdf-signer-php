<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final class ValidatePdfRequestDto
{
    public function __construct(
        public readonly PdfContentDto $pdf,
        public readonly SignatureValidationOptionsDto $options = new SignatureValidationOptionsDto,
    ) {}
}
