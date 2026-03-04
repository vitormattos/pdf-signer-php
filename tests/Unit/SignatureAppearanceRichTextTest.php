<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Application\DTO\SignatureAppearanceXObjectDto;
use SignerPHP\Infrastructure\PdfCore\PageDescriptor;
use SignerPHP\Infrastructure\PdfCore\PageInfo;
use SignerPHP\Infrastructure\PdfCore\PdfDocument;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use SignerPHP\Infrastructure\PdfCore\SignatureAppearance;

final class SignatureAppearanceRichTextTest extends TestCase
{
    public function test_signature_appearance_uses_prepared_xobject_stream_and_resources(): void
    {
        $document = $this->buildDocumentWithSinglePage();
        $annotation = $document->createObject(['Type' => '/Annot']);

        $stream = "q\nBT\n/F1 12 Tf\n10 20 Td\n(Signed by Admin) Tj\nET\nQ\n";
        $resources = [
            'Font' => [
                'F1' => [
                    'Type' => '/Font',
                    'Subtype' => '/Type1',
                    'BaseFont' => '/Helvetica',
                ],
            ],
        ];

        $appearance = SignatureAppearance::new()
            ->withXObject(new SignatureAppearanceXObjectDto($stream, $resources))
            ->withRect([10, 20, 110, 70])
            ->withPdfDocument($document)
            ->withAnnotationObject($annotation)
            ->withPageRotate(new PDFValueSimple(0));

        $result = $appearance->generate();

        self::assertSame($annotation, $result);
        self::assertNotNull($result['AP']);
        self::assertNotNull($result['AP']['N']);
    }

    public function test_signature_appearance_works_with_image_when_no_prepared_xobject_is_provided(): void
    {
        $document = $this->buildDocumentWithSinglePage();
        $annotation = $document->createObject(['Type' => '/Annot']);

        $appearance = SignatureAppearance::new()
            ->withBackgroundImage($this->tinyPngBase64())
            ->withRect([10, 20, 110, 70])
            ->withPdfDocument($document)
            ->withAnnotationObject($annotation)
            ->withPageRotate(new PDFValueSimple(0));

        $result = $appearance->generate();
        self::assertNotNull($result);
    }

    public function test_signature_appearance_combines_background_image_and_xobject_text_layers(): void
    {
        $document = $this->buildDocumentWithSinglePage();
        $annotation = $document->createObject(['Type' => '/Annot']);

        $xObject = new SignatureAppearanceXObjectDto(
            "BT\n/F1 10 Tf\n10 20 Td\n(Signed by Demo User) Tj\nET\n",
            [
                'Font' => [
                    'F1' => [
                        'Type' => '/Font',
                        'Subtype' => '/Type1',
                        'BaseFont' => '/Helvetica',
                    ],
                ],
            ]
        );

        $appearance = SignatureAppearance::new()
            ->withBackgroundImage($this->tinyPngBase64())
            ->withXObject($xObject)
            ->withRect([10, 20, 210, 120])
            ->withPdfDocument($document)
            ->withAnnotationObject($annotation)
            ->withPageRotate(new PDFValueSimple(0));

        $result = $appearance->generate();

        self::assertNotNull($result['AP']);
        self::assertNotNull($result['AP']['N']);

        $pdfEntries = $this->collectPdfEntries($document);
        self::assertStringContainsString('/n0 Do', $pdfEntries);
        self::assertStringContainsString('/n2 Do', $pdfEntries);
        self::assertStringContainsString('/Subtype/Image', $pdfEntries);
        self::assertStringContainsString('/BaseFont/Helvetica', $pdfEntries);
        self::assertStringContainsString('Signed by Demo User', $pdfEntries);
    }

    private function buildDocumentWithSinglePage(): PdfDocument
    {
        $document = new PdfDocument;
        $page = new PDFObject(3, ['Type' => '/Page']);
        $document->addObject($page);

        $pageInfo = PageInfo::new()->withPdfDocument($document);
        $pageDescriptor = new PageDescriptor(3, [new class
        {
            public function val(): string
            {
                return '0 0 595 842';
            }
        }]);
        $this->setPrivateProperty($pageInfo, 'pagesInfo', [$pageDescriptor]);
        $this->setPrivateProperty($document, 'pageInfo', $pageInfo);

        return $document;
    }

    private function setPrivateProperty(object $target, string $property, mixed $value): void
    {
        (function (object $target, string $property, mixed $value): void {
            $target->{$property} = $value;
        })->bindTo($target, $target::class)($target, $property, $value);
    }

    private function collectPdfEntries(PdfDocument $document): string
    {
        $entries = array_map(
            static fn (PDFObject $object): string => $object->toPdfEntry(),
            $document->getPdfObjects()
        );

        return implode("\n", $entries);
    }

    private function tinyPngBase64(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7+0mQAAAAASUVORK5CYII=';
    }
}
