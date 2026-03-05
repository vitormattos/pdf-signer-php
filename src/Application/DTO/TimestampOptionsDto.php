<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final class TimestampOptionsDto
{
    public readonly HashAlgorithm $hashAlgorithm;

    /**
     * @var array<string, string>
     */
    public readonly array $customHeaders;

    public function __construct(
        public readonly string $tsaUrl,
        HashAlgorithm|string $hashAlgorithm = HashAlgorithm::Sha256,
        public readonly bool $certReq = true,
        public readonly ?string $username = null,
        public readonly ?string $password = null,
        public readonly int $timeoutSeconds = 15,
        public readonly ?string $oauthClientId = null,
        public readonly ?string $oauthClientSecret = null,
        public readonly ?string $oauthTokenUrl = null,
        array $customHeaders = [],
    ) {
        $this->hashAlgorithm = HashAlgorithm::fromString($hashAlgorithm);
        $this->customHeaders = $this->normalizeCustomHeaders($customHeaders);
    }

    /**
     * @param  array<mixed>  $customHeaders
     * @return array<string, string>
     */
    private function normalizeCustomHeaders(array $customHeaders): array
    {
        $normalized = [];
        foreach ($customHeaders as $name => $value) {
            if (! is_string($name) || trim($name) === '' || ! is_string($value)) {
                continue;
            }

            $normalized[$this->canonicalizeHeaderName($name)] = $value;
        }

        return $normalized;
    }

    private function canonicalizeHeaderName(string $name): string
    {
        $parts = explode('-', str_replace('_', '-', strtolower(trim($name))));
        $parts = array_map(static fn (string $part): string => ucfirst($part), $parts);

        return implode('-', $parts);
    }
}
