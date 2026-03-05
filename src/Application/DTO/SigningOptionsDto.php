<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final class SigningOptionsDto
{
    public function __construct(
        public readonly ?SignatureMetadataDto $metadata = null,
        public readonly ?SignatureAppearanceDto $appearance = null,
        public readonly ?TimestampOptionsDto $timestamp = null,
        public readonly bool $useDefaultAppearance = true,
        public readonly SignatureProfile $signatureProfile = SignatureProfile::PdfBasic,
        public readonly ?CertificationLevel $certificationLevel = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }
}
