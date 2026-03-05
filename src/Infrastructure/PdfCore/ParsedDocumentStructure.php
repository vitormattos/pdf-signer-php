<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValue;

final class ParsedDocumentStructure
{
    /**
     * @param  array<int, int|array{stmoid:int,pos:int}|null>  $xrefTable
     * @param  array<int, int>  $revisions
     */
    public function __construct(
        public readonly ?PDFValue $trailer,
        public readonly string $version,
        public readonly array $xrefTable,
        public readonly int $xrefPosition,
        public readonly string $xrefVersion,
        public readonly array $revisions,
    ) {}

    /**
     * @return array{trailer:PDFValue|null,version:string,xref:array<int, int|array{stmoid:int,pos:int}|null>,xrefposition:int,xrefversion:string,revisions:array<int,int>}
     */
    public function toArray(): array
    {
        return [
            'trailer' => $this->trailer,
            'version' => $this->version,
            'xref' => $this->xrefTable,
            'xrefposition' => $this->xrefPosition,
            'xrefversion' => $this->xrefVersion,
            'revisions' => $this->revisions,
        ];
    }
}
