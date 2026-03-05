<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Xref;

use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValue;

final class XrefParseResult
{
    /**
     * @param  array<int, int|array{stmoid:int,pos:int}|null>  $table
     */
    public function __construct(
        public readonly array $table,
        public readonly PDFValue $trailer,
        public readonly string $minimumPdfVersion,
    ) {}

    /**
     * @return array{0:array<int, int|array{stmoid:int,pos:int}|null>,1:PDFValue,2:string}
     */
    public function toLegacyTuple(): array
    {
        return [$this->table, $this->trailer, $this->minimumPdfVersion];
    }

    /**
     * @return array{0:array<int, int|array{stmoid:int,pos:int}|null>,1:PDFValue,2:string}
     */
    public function toLegacyXrefTuple(): array
    {
        return $this->toLegacyTuple();
    }
}
