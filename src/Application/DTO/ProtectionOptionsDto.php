<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

use InvalidArgumentException;

final class ProtectionOptionsDto
{
    public function __construct(
        public readonly ?string $ownerPassword = null,
        public readonly string $userPassword = '',
        public readonly bool $allowPrint = true,
        public readonly bool $allowCopy = true,
        public readonly bool $allowModify = true,
        public readonly int $keyLengthBits = 256,
        public readonly bool $encryptMetadata = true,
    ) {
        if ($ownerPassword === '') {
            throw new InvalidArgumentException('Owner password cannot be an empty string.');
        }

        if (! in_array($this->keyLengthBits, [128, 256], true)) {
            throw new InvalidArgumentException('Only 128-bit and 256-bit PDF protection are supported.');
        }
    }

    public static function preventCopy(
        ?string $ownerPassword = null,
        string $userPassword = '',
        bool $allowPrint = true,
        bool $allowModify = true,
    ): self {
        return new self(
            ownerPassword: $ownerPassword,
            userPassword: $userPassword,
            allowPrint: $allowPrint,
            allowCopy: false,
            allowModify: $allowModify,
        );
    }
}
