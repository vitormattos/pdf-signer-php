<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

use ArrayAccess;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValue;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use Stringable;

class PDFObject implements ArrayAccess, Stringable
{
    protected mixed $stream = null;

    protected PDFValueObject $value;

    protected int $generation;

    public function __construct(protected int $oid, array|PDFValue|null $value = null, int $generation = 0)
    {
        if ($value === null) {
            $value = new PDFValueObject;
        }

        if (is_array($value)) {
            $obj = new PDFValueObject;
            foreach ($value as $field => $v) {
                $obj[$field] = $v;
            }

            $value = $obj;
        }

        if (! $value instanceof PDFValueObject) {
            $value = new PDFValueObject((array) $value->val());
        }

        $this->value = $value;
        $this->generation = $generation;
    }

    public function getKeys(): array
    {
        return $this->value->getKeys();
    }

    public function setOid(int $oid): void
    {
        $this->oid = $oid;
    }

    public function getGeneration(): int
    {
        return $this->generation;
    }

    public function __toString(): string
    {
        return $this->oid.' 0 obj
'.
            ($this->value.PHP_EOL).
            ($this->stream === null ? '' :
                'stream
...
endstream
'
            ).
            "endobj\n";
    }

    public function toPdfEntry(): string
    {
        return $this->oid.' 0 obj'.PHP_EOL.
                $this->value.PHP_EOL.
                ($this->stream === null ? '' :
                    "stream\r\n".
                    $this->stream.
                    PHP_EOL.'endstream'.PHP_EOL
                ).
                'endobj'.PHP_EOL;
    }

    public function getOid(): int
    {
        return $this->oid;
    }

    public function getValue(): PDFValueObject
    {
        return $this->value;
    }

    public function hasField(string $field): bool
    {
        return $this->value->has($field);
    }

    public function getField(string $field): ?PDFValue
    {
        return $this->value->get($field);
    }

    public function setField(string $field, mixed $value): self
    {
        $this->value->set($field, $value);

        return $this;
    }

    public function removeField(string $field): void
    {
        $this->value->remove($field);
    }

    protected static function flateDecode($stream, $params): string
    {
        switch ($params['Predictor']->asIntOrNull()) {
            case 1:
                return $stream;
            case 10:
            case 11:
            case 12:
            case 13:
            case 14:
            case 15:
                break;
            default:
                throw new PdfCoreStructureException('Only PNG predictors are supported.');
        }

        switch ($params['Colors']->asIntOrNull()) {
            case 1:
                break;
            default:
                throw new PdfCoreStructureException('Only one color channel is supported for predictor decoding.');
        }

        switch ($params['BitsPerComponent']->asIntOrNull()) {
            case 8:
                break;
            default:
                throw new PdfCoreStructureException('Only 8 bits per component are supported for predictor decoding.');
        }

        $decoded = new Buffer;
        $columns = $params['Columns']->asIntOrNull();
        if ($columns === null) {
            throw new PdfCoreParsingException('Invalid column count for stream decoding');
        }

        $streamLen = strlen((string) $stream);

        $dataPrev = str_pad('', $columns, chr(0));
        $posI = 0;
        while ($posI < $streamLen) {
            $filterByte = ord($stream[$posI++]);
            $data = substr((string) $stream, $posI, $columns);
            $posI += strlen($data);
            $data = str_pad($data, $columns, chr(0));

            switch ($filterByte) {
                case 0:
                    break;
                case 1:
                    for ($i = 1; $i < $columns; $i++) {
                        $data[$i] = chr((ord($data[$i]) + ord($data[$i - 1])) % 256);
                    }

                    break;
                case 2:
                    for ($i = 0; $i < $columns; $i++) {
                        $data[$i] = chr((ord($data[$i]) + ord($dataPrev[$i])) % 256);
                    }

                    break;
                default:
                    throw new PdfCoreParsingException('Unsupported PNG predictor filter in stream.');
            }

            $decoded->data($data);
            $dataPrev = $data;
        }

        return $decoded->raw();
    }

    /**
     * Decompress a FlateDecode stream using format-aware detection.
     *
     * **Compression Format Specification**
     *
     * ISO 32000-1:2008 §7.4.4 and ISO 32000-2:2020 §7.4.4 mandate that FlateDecode streams
     * must use the zlib format as defined in RFC 1950, which combines the DEFLATE algorithm
     * (RFC 1951) with a 2-byte CMF+FLG header and an Adler-32 checksum. This is the only
     * conforming format.
     *
     * In practice, non-conforming PDF generators (particularly tools targeting Windows or
     * legacy systems) produce streams in two other formats:
     *
     *   - **Raw DEFLATE (RFC 1951)**: No header or checksum. Difficult to distinguish from
     *     corrupted data without attempting decompression.
     *   - **GZIP (RFC 1952)**: Includes its own 2-byte magic header (0x1F 0x8B) for tar/gzip
     *     compatibility. Rare in PDFs but seen in output from cross-platform tools.
     *
     * **Format Detection**
     *
     * Each format is identifiable by its initial bytes, allowing deterministic dispatch instead
     * of blind trial-and-error:
     *
     *   | Format | Signature | PHP Function | RFC |
     *   |---|---|---|---|
     *   | GZIP | magic bytes 0x1F 0x8B | gzdecode() | RFC 1952 |
     *   | zlib | CMF byte & 0x0F == 8, (CMF*256+FLG) % 31 == 0 | gzuncompress() | RFC 1950 |
     *   | raw DEFLATE | none (absence of above) | gzinflate() | RFC 1951 |
     *
     * This approach is more robust than sequential attempt-and-error because:
     *   1. Non-blocking: avoids suppressing real errors with `@` operator
     *   2. Semantically correct: error is thrown only when format is correctly identified
     *     but fails to decompress, not after exhausting all variants
     *   3. Consistent with major PDF engines (libpoppler, PDFium, Apache PDFBox)
     *
     * @throws PdfCoreParsingException if the stream cannot be decompressed after format detection
     */
    private static function inflateFlateStream(string $stream): string
    {
        $b0 = strlen($stream) > 1 ? ord($stream[0]) : -1;
        $b1 = strlen($stream) > 1 ? ord($stream[1]) : -1;

        // Gzip: magic bytes 0x1F 0x8B (RFC 1952).
        if ($b0 === 0x1F && $b1 === 0x8B) {
            $inflated = @gzdecode($stream);
            if (is_string($inflated)) {
                return $inflated;
            }

            throw new PdfCoreParsingException('Failed to inflate FlateDecode stream.');
        }

        // zlib: CMF+FLG header where (CMF*256+FLG) % 31 === 0 and CM (lower 4
        // bits of CMF) === 8 (deflate). (RFC 1950)
        if ($b0 !== -1 && ($b0 & 0x0F) === 8 && ($b0 * 256 + $b1) % 31 === 0) {
            $inflated = @gzuncompress($stream);
            if (is_string($inflated)) {
                return $inflated;
            }

            throw new PdfCoreParsingException('Failed to inflate FlateDecode stream.');
        }

        // Raw deflate (RFC 1951): no wrapper header.
        $inflated = @gzinflate($stream);
        if (is_string($inflated)) {
            return $inflated;
        }

        throw new PdfCoreParsingException('Failed to inflate FlateDecode stream.');
    }

    public function getStream($raw = true): string
    {
        if ($raw === true) {
            return (string) $this->stream;
        }

        if (isset($this->value['Filter'])) {
            switch ($this->value['Filter']) {
                case '/FlateDecode':
                    $DecodeParams = $this->value['DecodeParms'] ?? [];
                    $params = [
                        'Columns' => $DecodeParams['Columns'] ?? new PDFValueSimple(0),
                        'Predictor' => $DecodeParams['Predictor'] ?? new PDFValueSimple(1),
                        'BitsPerComponent' => $DecodeParams['BitsPerComponent'] ?? new PDFValueSimple(8),
                        'Colors' => $DecodeParams['Colors'] ?? new PDFValueSimple(1),
                    ];

                    $inflated = self::inflateFlateStream((string) $this->stream);

                    return self::flateDecode($inflated, $params);
                default:
                    throw new PdfCoreStructureException('Unknown compression method '.$this->value['Filter']);
            }
        }

        return (string) $this->stream;
    }

    public function setStream($stream, $raw = true): void
    {
        if ($raw === true) {
            $this->stream = $stream;

            return;
        }

        if (isset($this->value['Filter'])) {
            if ($this->value['Filter'] == '/FlateDecode') {
                $stream = gzcompress((string) $stream);
            }
        }

        $this->value['Length'] = strlen((string) $stream);
        $this->stream = $stream;
    }

    public function offsetSet($field, $value): void
    {
        $this->setField((string) $field, $value);
    }

    public function offsetExists($field): bool
    {
        return $this->value->offsetExists($field);
    }

    public function offsetGet($field): mixed
    {
        return $this->value[$field];
    }

    public function offsetUnset($field): void
    {
        $this->removeField((string) $field);
    }

    public function push($v)
    {
        return $this->value->push($v);
    }
}
