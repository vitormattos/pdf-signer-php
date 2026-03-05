<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\ValueObject;

final class ExtractedPdfSignature
{
    /**
     * @param  array{0:int,1:int,2:int,3:int}  $byteRange
     */
    public function __construct(
        public readonly int $index,
        public readonly array $byteRange,
        public readonly string $signatureHex,
        public readonly string $signedContent,
        public readonly bool $byteRangeValid,
        public readonly ?string $byteRangeError = null,
    ) {}
}
