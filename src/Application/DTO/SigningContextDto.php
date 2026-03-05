<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

use SignerPHP\Domain\ValueObject\VerifiedCertificate;

final class SigningContextDto
{
    public function __construct(
        public readonly SignPdfRequestDto $request,
        public readonly VerifiedCertificate $verifiedCertificate,
    ) {}
}
