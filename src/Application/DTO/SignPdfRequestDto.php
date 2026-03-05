<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final class SignPdfRequestDto
{
    public function __construct(
        public readonly PdfContentDto $pdf,
        public readonly CertificateCredentialsDto $certificate,
        public readonly SigningOptionsDto $options,
    ) {}

    public static function fromRequired(
        PdfContentDto $pdf,
        CertificateCredentialsDto $certificate,
        ?SigningOptionsDto $options = null,
    ): self {
        return new self($pdf, $certificate, $options ?? SigningOptionsDto::empty());
    }
}
