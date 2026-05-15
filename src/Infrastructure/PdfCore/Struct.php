<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

use Exception;
use SignerPHP\Infrastructure\PdfCore\Xref\CrossReferenceManager;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 **/
class Struct
{
    private PdfDocument $pdfDocument;

    private const REGEX_PDF_VERSION = '/%PDF-(\d+\.\d+)/';

    public static function new(): static
    {
        return new static;
    }

    public function withPdfDocument(PdfDocument $pdfDocument): self
    {
        $this->pdfDocument = $pdfDocument;

        return $this;
    }

    /**
     * @return array{trailer:\SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValue|null,version:string,xref:array<int, int|array{stmoid:int,pos:int}|null>,xrefposition:int,xrefversion:string,revisions:array<int,int>}
     */
    public function structure(): array
    {
        return $this->parse()->toArray();
    }

    /**
     * Parse PDF structure (version, cross-references, trailer).
     *
     * ISO 32000 §7.5.2 places the version marker `%PDF-x.y` at file start, but some tools
     * prepend UTF-8 BOM or binary bytes. Robustness principle (RFC 1122): scan first 1024
     * bytes for `%PDF-` pattern instead of strict first-line matching. Consistent with
     * libpoppler, PDFium, Apache PDFBox.
     */
    public function parse(): ParsedDocumentStructure
    {
        $buffer = $this->pdfDocument->getBuffer()->raw();
        if ($buffer === '') {
            throw new Exception('Failed to get PDF version');
        }

        $headerWindow = substr($buffer, 0, 1024);
        if (preg_match(self::REGEX_PDF_VERSION, $headerWindow, $matches) !== 1) {
            throw new Exception('PDF version not found');
        }

        $pdfVersion = 'PDF-'.$matches[1];

        preg_match_all('/startxref\s*([0-9]+)\s*%%EOF($|[\r\n])/ms', $buffer, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $versions = [];
        foreach ($matches as $match) {
            $versions[] = intval($match[2][1]) + strlen($match[2][0]);
        }

        $startXRefPos = strrpos($buffer, 'startxref');
        if ($startXRefPos === false) {
            throw new Exception('startxref not found');
        }

        if (preg_match('/startxref\s*([0-9]+)\s*%%EOF\s*$/ms', $buffer, $matches, 0, $startXRefPos) !== 1) {
            throw new Exception('startxref and %%EOF not found');
        }

        $xrefPos = intval($matches[1]);

        if ($xrefPos === 0) {
            return new ParsedDocumentStructure(
                trailer: null,
                version: $pdfVersion,
                xrefTable: [],
                xrefPosition: 0,
                xrefVersion: $pdfVersion,
                revisions: $versions,
            );
        }

        $xref = CrossReferenceManager::new()
            ->withXrefPosition($xrefPos)
            ->withPdfDocument($this->pdfDocument)
            ->parse();

        return new ParsedDocumentStructure(
            trailer: $xref->trailer,
            version: $pdfVersion,
            xrefTable: $xref->table,
            xrefPosition: $xrefPos,
            xrefVersion: $xref->minimumPdfVersion,
            revisions: $versions,
        );
    }
}
