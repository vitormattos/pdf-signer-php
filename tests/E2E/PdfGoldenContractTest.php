<?php

declare(strict_types=1);

namespace SignerPHP\Tests\E2E;

use PHPUnit\Framework\TestCase;
use SignerPHP\Application\DTO\SignatureActorDto;
use SignerPHP\Application\DTO\SignatureMetadataDto;
use SignerPHP\Presentation\Signer;

final class PdfGoldenContractTest extends TestCase
{
    public function test_unsigned_pdf_matches_golden_contract(): void
    {
        $inputPath = __DIR__.'/../../exemplos/pdfs/Untitled.pdf';
        self::assertFileExists($inputPath);

        $reportJson = $this->runCommand([
            PHP_BINARY,
            __DIR__.'/../../bin/signer-inspect',
            '--input='.$inputPath,
            '--json',
        ], $exitCode);
        self::assertSame(0, $exitCode, 'signer-inspect must run successfully');

        $report = json_decode($reportJson, true);
        self::assertIsArray($report);

        self::assertArrayHasKey('observability', $report);
        self::assertIsArray($report['observability']);

        self::assertArrayHasKey('xref', $report['observability']);
        self::assertArrayHasKey('mode', $report['observability']['xref']);
        self::assertContains($report['observability']['xref']['mode'], ['table', 'stream', 'hybrid', 'unknown']);

        self::assertArrayHasKey('incremental_revisions', $report['observability']);
        self::assertIsArray($report['observability']['incremental_revisions']);
        self::assertArrayHasKey('count', $report['observability']['incremental_revisions']);
        self::assertArrayHasKey('boundaries', $report['observability']['incremental_revisions']);
        self::assertIsArray($report['observability']['incremental_revisions']['boundaries']);

        self::assertArrayHasKey('byte_range', $report['observability']);
        self::assertArrayHasKey('planning', $report['observability']['byte_range']);
        self::assertArrayHasKey('final_verification', $report['observability']['byte_range']);
        self::assertIsArray($report['observability']['byte_range']['final_verification']);

        self::assertArrayHasKey('signature_dictionary_assembly', $report['observability']);
        self::assertArrayHasKey('details', $report['observability']['signature_dictionary_assembly']);
        self::assertIsArray($report['observability']['signature_dictionary_assembly']['details']);

        self::assertArrayHasKey('appearance_stream_generation', $report['observability']);
        self::assertArrayHasKey('has_appearance', $report['observability']['appearance_stream_generation']);

        self::assertArrayHasKey('dss_vri_assembly', $report['observability']);
        self::assertArrayHasKey('has_dss', $report['observability']['dss_vri_assembly']);
        self::assertArrayHasKey('has_vri', $report['observability']['dss_vri_assembly']);

        $actual = [
            'has_signatures' => (bool) ($report['signatures']['has_signatures'] ?? false),
            'count' => (int) ($report['signatures']['count'] ?? 0),
            'all_valid' => (bool) ($report['signatures']['all_valid'] ?? false),
            'inferred_profile' => (string) ($report['inferred_profile'] ?? ''),
        ];

        self::assertSame($this->loadGolden('pdf_unsigned_contract.json'), $actual);
    }

    public function test_signed_pdf_matches_golden_contract(): void
    {
        if (! function_exists('openssl_pkcs12_read')) {
            self::markTestSkipped('OpenSSL extension is required.');
        }

        $inputPath = __DIR__.'/../../exemplos/pdfs/Untitled.pdf';
        $certPath = __DIR__.'/../../exemplos/cert.pfx';
        self::assertFileExists($inputPath);
        self::assertFileExists($certPath);

        $input = file_get_contents($inputPath);
        self::assertIsString($input);

        $signed = Signer::signer()
            ->withPdfContent($input)
            ->withCertificatePath($certPath, '1234**')
            ->withMetadata(new SignatureMetadataDto(
                reason: 'E2E Golden',
                actor: new SignatureActorDto(name: 'SignerPHP Tests')
            ))
            ->sign();

        $validation = Signer::validation()
            ->withPdfContent($signed)
            ->validate();

        $signedTempPath = tempnam(sys_get_temp_dir(), 'signerphp-e2e-signed-');
        self::assertIsString($signedTempPath);
        self::assertNotSame('', $signedTempPath);
        file_put_contents($signedTempPath, $signed);

        $reportJson = $this->runCommand([
            PHP_BINARY,
            __DIR__.'/../../bin/signer-inspect',
            '--input='.$signedTempPath,
            '--json',
        ], $inspectExitCode);
        @unlink($signedTempPath);
        self::assertSame(0, $inspectExitCode, 'signer-inspect must run successfully for signed PDF');

        $report = json_decode($reportJson, true);
        self::assertIsArray($report);
        self::assertArrayHasKey('observability', $report);
        self::assertIsArray($report['observability']);
        self::assertArrayHasKey('byte_range', $report['observability']);
        self::assertIsArray($report['observability']['byte_range']);

        $planning = $report['observability']['byte_range']['planning'] ?? null;
        self::assertIsArray($planning);
        self::assertTrue((bool) ($planning['available'] ?? false), 'ByteRange planning should be available for signed PDF');
        self::assertSame('derived-from-final-pdf', $planning['source'] ?? null);
        self::assertArrayHasKey('entries', $planning);
        self::assertIsArray($planning['entries']);
        self::assertNotEmpty($planning['entries']);
        self::assertArrayHasKey('revision_mapping', $planning);
        self::assertIsArray($planning['revision_mapping']);
        self::assertNotEmpty($planning['revision_mapping']);

        $finalVerification = $report['observability']['byte_range']['final_verification'] ?? null;
        self::assertIsArray($finalVerification);
        self::assertNotEmpty($finalVerification);
        self::assertCount(count($finalVerification), $planning['entries']);
        self::assertCount(count($planning['entries']), $planning['revision_mapping']);

        foreach ($planning['entries'] as $planningEntry) {
            self::assertIsArray($planningEntry);
            self::assertTrue((bool) ($planningEntry['matches_final_second_part_offset'] ?? false));
            self::assertTrue((bool) ($planningEntry['matches_observed_contents_hex_length'] ?? false));
            self::assertTrue((bool) ($planningEntry['gap_matches_reserved_container'] ?? false));
            self::assertIsInt($planningEntry['signed_span_end_offset'] ?? null);
        }

        foreach ($planning['revision_mapping'] as $mappingEntry) {
            self::assertIsArray($mappingEntry);
            self::assertTrue((bool) ($mappingEntry['fits_revision_boundary'] ?? false));
            self::assertIsInt($mappingEntry['revision_index'] ?? null);
            self::assertIsInt($mappingEntry['revision_end'] ?? null);
        }

        $actual = [
            'has_signatures' => $validation->hasSignatures,
            'count' => count($validation->entries),
            'all_valid' => $validation->allValid,
            'has_byte_range' => str_contains($signed, '/ByteRange'),
            'has_contents' => str_contains(str_replace(' ', '', $signed), '/Contents<'),
        ];

        self::assertSame($this->loadGolden('pdf_signed_contract.json'), $actual);
    }

    public function test_multi_signed_pdf_exposes_planning_for_each_signature(): void
    {
        if (! function_exists('openssl_pkcs12_read')) {
            self::markTestSkipped('OpenSSL extension is required.');
        }

        $inputPath = __DIR__.'/../../exemplos/pdfs/Untitled.pdf';
        $certPath = __DIR__.'/../../exemplos/cert.pfx';
        self::assertFileExists($inputPath);
        self::assertFileExists($certPath);

        $input = file_get_contents($inputPath);
        self::assertIsString($input);

        $firstSigned = Signer::signer()
            ->withPdfContent($input)
            ->withCertificatePath($certPath, '1234**')
            ->withMetadata(new SignatureMetadataDto(
                reason: 'E2E First Signature',
                actor: new SignatureActorDto(name: 'SignerPHP Tests')
            ))
            ->sign();

        $secondSigned = Signer::signer()
            ->withPdfContent($firstSigned)
            ->withCertificatePath($certPath, '1234**')
            ->withMetadata(new SignatureMetadataDto(
                reason: 'E2E Second Signature',
                actor: new SignatureActorDto(name: 'SignerPHP Tests')
            ))
            ->sign();

        $signedTempPath = tempnam(sys_get_temp_dir(), 'signerphp-e2e-multisigned-');
        self::assertIsString($signedTempPath);
        self::assertNotSame('', $signedTempPath);
        file_put_contents($signedTempPath, $secondSigned);

        $reportJson = $this->runCommand([
            PHP_BINARY,
            __DIR__.'/../../bin/signer-inspect',
            '--input='.$signedTempPath,
            '--json',
        ], $inspectExitCode);
        @unlink($signedTempPath);
        self::assertSame(0, $inspectExitCode, 'signer-inspect must run successfully for multi-signed PDF');

        $report = json_decode($reportJson, true);
        self::assertIsArray($report);
        self::assertArrayHasKey('signatures', $report);
        self::assertGreaterThanOrEqual(2, (int) ($report['signatures']['count'] ?? 0));

        self::assertArrayHasKey('observability', $report);
        self::assertArrayHasKey('incremental_revisions', $report['observability']);
        self::assertGreaterThanOrEqual(2, (int) ($report['observability']['incremental_revisions']['count'] ?? 0));

        $planning = $report['observability']['byte_range']['planning'] ?? null;
        self::assertIsArray($planning);
        self::assertTrue((bool) ($planning['available'] ?? false));
        self::assertIsArray($planning['entries'] ?? null);
        self::assertGreaterThanOrEqual(2, count($planning['entries']));
        self::assertIsArray($planning['revision_mapping'] ?? null);
        self::assertGreaterThanOrEqual(2, count($planning['revision_mapping']));

        foreach ($planning['revision_mapping'] as $mappingEntry) {
            self::assertTrue((bool) ($mappingEntry['fits_revision_boundary'] ?? false));
        }
    }

    /**
     * @param  array<int, string>  $args
     */
    private function runCommand(array $args, ?int &$exitCode = null): string
    {
        $command = implode(' ', array_map('escapeshellarg', $args)).' 2>&1';
        $outputLines = [];
        $code = 1;
        exec($command, $outputLines, $code);
        $exitCode = $code;

        return implode("\n", $outputLines);
    }

    /**
     * @return array<string, bool|int|string>
     */
    private function loadGolden(string $fileName): array
    {
        $path = __DIR__.'/../Fixtures/golden/'.$fileName;
        self::assertFileExists($path);

        $json = file_get_contents($path);
        self::assertIsString($json);

        $decoded = json_decode($json, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
