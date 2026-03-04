<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\PdfCore\Utils\ImageParser;

/**
 * Verifies that ImageParser correctly splits colour and alpha pixel channels
 * for every supported PNG depth and colour type.
 *
 * Each test builds a minimal raw PNG in memory with precisely-known pixel values,
 * parses it, decompresses the resulting channel streams and asserts exact byte
 * content – confirming that no data is lost or corrupted during channel splitting.
 *
 * The 16-bit test cases cover the regression where a trailing '.' in the
 * alpha-extraction regex consumed one extra byte per pixel, causing the second
 * (and subsequent) pixel's alpha value to be dropped entirely.
 */
final class ImageParserPixelIntegrityTest extends TestCase
{
    // ------------------------------------------------------------------
    // 8-bit RGBA (colorType = 6, bitsPerChannel = 8)
    // ------------------------------------------------------------------

    /**
     * Two-pixel 8-bit RGBA row.
     *
     *   pixel 0 : R=200  G=100  B=50   A=180
     *   pixel 1 : R=10   G=20   B=30   A=240
     *
     * The parser must separate the interleaved RGBA bytes into a colour stream
     * (RGB only) and an alpha stream, both prefixed with a PNG filter byte.
     */
    public function test_8bit_rgba_two_pixels_colour_and_alpha_correctly_split(): void
    {
        $parser = new ImageParser;

        // One scanline: filter-byte=0 (None) then R0 G0 B0 A0 R1 G1 B1 A1
        $scanline = "\x00\xC8\x64\x32\xB4\x0A\x14\x1E\xF0";
        $png = $this->buildPng(width: 2, height: 1, bits: 8, colorType: 6, idat: (string) gzcompress($scanline));

        $info = $parser->parsePng($png);

        // Colour stream: filter-byte + R0 G0 B0 + R1 G1 B1  (alpha stripped)
        $colorData = gzuncompress($info['data']);
        self::assertSame(
            "\x00\xC8\x64\x32\x0A\x14\x1E",
            $colorData,
            'Colour channel must contain only the RGB bytes for both pixels'
        );

        // Alpha stream: filter-byte + A0 + A1
        self::assertArrayHasKey('smask', $info, '8-bit RGBA image must produce an SMask');
        $alphaData = gzuncompress($info['smask']);
        self::assertSame(
            "\x00\xB4\xF0",
            $alphaData,
            'Alpha channel must contain both alpha values (one per pixel)'
        );
    }

    // ------------------------------------------------------------------
    // 8-bit Gray+Alpha (colorType = 4, bitsPerChannel = 8)
    // ------------------------------------------------------------------

    /**
     * Two-pixel 8-bit Gray+Alpha row.
     *
     *   pixel 0 : gray=200  alpha=180
     *   pixel 1 : gray=30   alpha=240
     */
    public function test_8bit_gray_alpha_two_pixels_colour_and_alpha_correctly_split(): void
    {
        $parser = new ImageParser;

        // Scanline: filter-byte=0 then Gray0 Alpha0 Gray1 Alpha1
        $scanline = "\x00\xC8\xB4\x1E\xF0";
        $png = $this->buildPng(width: 2, height: 1, bits: 8, colorType: 4, idat: (string) gzcompress($scanline));

        $info = $parser->parsePng($png);

        // Colour stream: filter-byte + Gray0 + Gray1
        $colorData = gzuncompress($info['data']);
        self::assertSame(
            "\x00\xC8\x1E",
            $colorData,
            'Gray channel must contain both gray values'
        );

        // Alpha stream: filter-byte + Alpha0 + Alpha1
        self::assertArrayHasKey('smask', $info, '8-bit Gray+Alpha must produce an SMask');
        $alphaData = gzuncompress($info['smask']);
        self::assertSame(
            "\x00\xB4\xF0",
            $alphaData,
            'Alpha channel must contain both alpha values'
        );
    }

    // ------------------------------------------------------------------
    // 16-bit RGBA (colorType = 6, bitsPerChannel = 16)  ← regression case
    // ------------------------------------------------------------------

    /**
     * Two-pixel 16-bit RGBA row.
     *
     *   pixel 0 : R=0xFFFF  G=0x0000  B=0x0000  A=0x7FFF  (full red,   ~50 % alpha)
     *   pixel 1 : R=0x0000  G=0xFFFF  B=0x0000  A=0x3FFF  (full green, ~25 % alpha)
     *
     * Regression: the alpha-extraction regex /.{6}(..)./s had a trailing dot
     * that consumed one extra byte per pixel-group.  With an 8-byte-per-pixel
     * binary scanline the trailing '.' caused the regex to require 9 bytes to
     * match at position 8, leaving only 7 bytes and producing no match – so the
     * second pixel's alpha was silently dropped.
     */
    public function test_16bit_rgba_two_pixels_colour_and_alpha_correctly_split(): void
    {
        $parser = new ImageParser;

        // Scanline: filter-byte=0, then for each pixel R(2) G(2) B(2) A(2)
        $scanline = "\x00"                                 // filter = None
            ."\xFF\xFF\x00\x00\x00\x00\x7F\xFF"          // pixel 0: R G B A
            ."\x00\x00\xFF\xFF\x00\x00\x3F\xFF";         // pixel 1: R G B A
        $png = $this->buildPng(width: 2, height: 1, bits: 16, colorType: 6, idat: (string) gzcompress($scanline));

        $info = $parser->parsePng($png);

        // Colour stream: filter-byte + R0(2) G0(2) B0(2) + R1(2) G1(2) B1(2)
        $colorData = gzuncompress($info['data']);
        self::assertSame(
            "\x00\xFF\xFF\x00\x00\x00\x00\x00\x00\xFF\xFF\x00\x00",
            $colorData,
            '16-bit colour channel: both pixels RGB values must be intact'
        );

        // Alpha stream: filter-byte + A0(2) + A1(2)
        self::assertArrayHasKey('smask', $info, '16-bit RGBA must produce an SMask');
        $alphaData = gzuncompress($info['smask']);
        self::assertSame(
            "\x00\x7F\xFF\x3F\xFF",
            $alphaData,
            '16-bit alpha channel: BOTH pixel alpha values must be present (regression: was dropping pixel 1)'
        );
    }

    // ------------------------------------------------------------------
    // 16-bit Gray+Alpha (colorType = 4, bitsPerChannel = 16)  ← regression case
    // ------------------------------------------------------------------

    /**
     * Two-pixel 16-bit Gray+Alpha row.
     *
     *   pixel 0 : gray=0xFFFF  alpha=0x7FFF  (full white, ~50 % alpha)
     *   pixel 1 : gray=0x0000  alpha=0x3FFF  (full black, ~25 % alpha)
     *
     * The same trailing-dot bug also affected the grayscale+alpha path.
     */
    public function test_16bit_gray_alpha_two_pixels_colour_and_alpha_correctly_split(): void
    {
        $parser = new ImageParser;

        // Scanline: filter-byte=0, then for each pixel Gray(2) Alpha(2)
        $scanline = "\x00"                      // filter = None
            ."\xFF\xFF\x7F\xFF"                // pixel 0: gray alpha
            ."\x00\x00\x3F\xFF";              // pixel 1: gray alpha
        $png = $this->buildPng(width: 2, height: 1, bits: 16, colorType: 4, idat: (string) gzcompress($scanline));

        $info = $parser->parsePng($png);

        // Colour stream: filter-byte + Gray0(2) + Gray1(2)
        $colorData = gzuncompress($info['data']);
        self::assertSame(
            "\x00\xFF\xFF\x00\x00",
            $colorData,
            '16-bit gray channel: both pixel gray values must be intact'
        );

        // Alpha stream: filter-byte + Alpha0(2) + Alpha1(2)
        self::assertArrayHasKey('smask', $info, '16-bit Gray+Alpha must produce an SMask');
        $alphaData = gzuncompress($info['smask']);
        self::assertSame(
            "\x00\x7F\xFF\x3F\xFF",
            $alphaData,
            '16-bit gray alpha channel: BOTH pixel alpha values must be present (regression: was dropping pixel 1)'
        );
    }

    // ------------------------------------------------------------------
    // Multi-row: verify filter byte appears once per row, not once overall
    // ------------------------------------------------------------------

    /**
     * Two-row 8-bit RGBA image (2 pixels per row).
     *
     * Ensures that each row's filter byte is copied into both channel streams,
     * not just the first row's byte.
     */
    public function test_8bit_rgba_two_rows_each_row_has_its_own_filter_byte(): void
    {
        $parser = new ImageParser;

        // Row 0: filter=0, (R=10 G=20 B=30 A=40), (R=50 G=60 B=70 A=80)
        // Row 1: filter=0, (R=90 G=100 B=110 A=120), (R=130 G=140 B=150 A=160)
        $row0 = "\x00\x0A\x14\x1E\x28\x32\x3C\x46\x50";
        $row1 = "\x00\x5A\x64\x6E\x78\x82\x8C\x96\xA0";
        $idat = (string) gzcompress($row0.$row1);
        $png = $this->buildPng(width: 2, height: 2, bits: 8, colorType: 6, idat: $idat);

        $info = $parser->parsePng($png);

        // Colour: 2 rows × (filter-byte + R G B + R G B)
        $colorData = gzuncompress($info['data']);
        $expectedColor = "\x00\x0A\x14\x1E\x32\x3C\x46"    // row 0
                       ."\x00\x5A\x64\x6E\x82\x8C\x96";   // row 1
        self::assertSame($expectedColor, $colorData, 'Each row must have its own filter byte in the colour stream');

        // Alpha: 2 rows × (filter-byte + A + A)
        $alphaData = gzuncompress($info['smask']);
        $expectedAlpha = "\x00\x28\x50"   // row 0: filter + A0 + A1
                       ."\x00\x78\xA0";  // row 1: filter + A0 + A1
        self::assertSame($expectedAlpha, $alphaData, 'Each row must have its own filter byte in the alpha stream');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function buildPng(int $width, int $height, int $bits, int $colorType, string $idat): string
    {
        $ihdrData = pack('NNCCCCC', $width, $height, $bits, $colorType, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n"
            .$this->pngChunk('IHDR', $ihdrData)
            .$this->pngChunk('IDAT', $idat)
            .$this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
