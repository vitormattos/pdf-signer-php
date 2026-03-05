<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\Native\ValueObject;

final class ProcessResult
{
    /**
     * @param  array<int, string>  $output
     */
    public function __construct(
        public readonly int $exitCode,
        public readonly array $output = [],
    ) {}

    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }

    public function outputAsString(): string
    {
        return implode("\n", $this->output);
    }
}
