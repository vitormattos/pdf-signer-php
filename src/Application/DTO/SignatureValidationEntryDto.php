<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final class SignatureValidationEntryDto
{
    /**
     * @param  array{0:int,1:int,2:int,3:int}  $byteRange
     */
    public function __construct(
        public readonly int $index,
        public readonly array $byteRange,
        public readonly bool $byteRangeValid,
        public readonly bool $cryptoValid,
        public readonly ?bool $trustValid,
        public readonly ?bool $policyValid,
        public readonly bool $valid,
        public readonly ?string $reason = null,
    ) {}
}
