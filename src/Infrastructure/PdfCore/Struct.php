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
     * Parse the PDF document structure, including version, cross-reference tables, and trailer.
     *
     * **PDF Version Detection Strategy**
     *
     * Per ISO 32000-1:2008 §7.5.2, the PDF version marker `%PDF-x.y` shall appear at the very
     * beginning of the file. However, this standard assumes conforming producers. In practice,
     * widely-used tools (especially Windows applications using Win32 APIs) prepend a UTF-8 BOM
     * (`0xEF 0xBB 0xBF`) to the file stream. The BOM is invisible to end users and not part of
     * the document, yet it physically precedes `%PDF-`.
     *
     * This implementation follows the Robustness Principle (RFC 1122 §1.2.2, Postel's Law):
     *   "Be conservative in what you send, be liberal in what you accept."
     *
     * ISO 32000-2:2020 §7.5.2 reinforces this guidance with an informative note acknowledging
     * that conforming readers should handle files with slight deviations from the normative
     * header placement. This approach is consistent with how major PDF implementations handle
     * the same issue:
     *
     *   - libpoppler (PDFDoc.cc): scans forward from the file start to find `%PDF-`
     *   - PDFium (cpdf_parser.cpp): searches within a header window for the version marker
     *   - Apache PDFBox: skips BOM bytes before header detection
     *
     * Strategy: Scan the first 1024 bytes (sufficient per ISO 32000 requirements) for the
     * pattern `/%PDF-(\d+\.\d+)/` instead of enforcing strict first-line matching. This is
     * the canonical pattern used by MIME sniffing tools (Unix `file` command, Apache Tika)
     * and tolerates both UTF-8 BOM and other binary prefixes while remaining unambiguous.
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
