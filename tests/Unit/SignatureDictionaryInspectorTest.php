<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\Native\Service\SignatureDictionaryInspector;

final class SignatureDictionaryInspectorTest extends TestCase
{
    public function test_extract_details_from_signature_dictionary(): void
    {
        $pdf = <<<'PDF'
%PDF-1.4
1 0 obj
<<
/Type /Sig
/Filter /Adobe.PPKLite
/SubFilter /ETSI.CAdES.detached
/M (D:20260506120000+00'00')
/Reason (Unit Test)
/Name (Signer PHP)
/Location (BR)
/Contents <41424344>
/ByteRange [0 10 20 30]
>>
endobj
PDF;

        $inspector = new SignatureDictionaryInspector;
        $details = $inspector->extractDetails($pdf);

        self::assertCount(1, $details);
        self::assertSame(0, $details[0]['signature_index']);
        self::assertSame('Adobe.PPKLite', $details[0]['filter']);
        self::assertSame('ETSI.CAdES.detached', $details[0]['subfilter']);
        self::assertSame("D:20260506120000+00'00'", $details[0]['signing_time']);
        self::assertSame('Unit Test', $details[0]['reason']);
        self::assertSame('Signer PHP', $details[0]['signer_name']);
        self::assertSame('BR', $details[0]['location']);
        self::assertTrue($details[0]['has_filter']);
        self::assertTrue($details[0]['has_subfilter']);
        self::assertTrue($details[0]['has_contents']);
        self::assertSame(8, $details[0]['contents_hex_length']);
    }

    public function test_extract_details_skips_non_signature_dictionary(): void
    {
        $pdf = <<<'PDF'
%PDF-1.4
1 0 obj
<< /ByteRange [0 10 20 30] /Type /Annot >>
endobj
PDF;

        $inspector = new SignatureDictionaryInspector;

        self::assertSame([], $inspector->extractDetails($pdf));
    }

    public function test_extract_details_supports_hex_encoded_strings_and_skips_invalid_boundaries(): void
    {
        $pdf = <<<'PDF'
%PDF-1.4
1 0 obj
<<
/Type /Sig
/Reason <5465737420486578>
/ByteRange [0 10 20 30]
/Contents <4142>
>>
endobj
2 0 obj
<< /Type /Sig /ByteRange [0 10 20 30]
PDF;

        $inspector = new SignatureDictionaryInspector;
        $details = $inspector->extractDetails($pdf);

        self::assertCount(1, $details);
        self::assertSame('Test Hex', $details[0]['reason']);
        self::assertNull($details[0]['filter']);
    }
}
