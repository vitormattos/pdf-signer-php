<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final class SignatureValidationOptionsDto
{
    public function __construct(
        public readonly bool $checkTrustChain = false,
        public readonly ?string $trustStorePath = null,
        public readonly ?string $trustAnchorsDirectory = null,
        public readonly ?array $trustAnchorsUrls = null,
        public readonly ?string $policy = null,
        public readonly bool $checkPolicyList = false,
        public readonly ?string $lpaUrlAsn1Pades = null,
        public readonly ?string $lpaUrlAsn1SignaturePades = null,
    ) {}
}
