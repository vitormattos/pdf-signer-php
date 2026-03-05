<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final class SignatureValidationResultDto
{
    /**
     * @param  array<int, SignatureValidationEntryDto>  $entries
     */
    public function __construct(
        public readonly bool $hasSignatures,
        public readonly bool $allValid,
        public readonly array $entries,
    ) {}
}
