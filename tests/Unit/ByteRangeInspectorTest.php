<?php

declare(strict_types=1);

namespace SignerPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SignerPHP\Infrastructure\Native\Service\ByteRangeInspector;

final class ByteRangeInspectorTest extends TestCase
{
    public function test_derive_planning_uses_dictionary_observed_length_and_eof_coverage(): void
    {
        $inspector = new ByteRangeInspector();

        $planning = $inspector->derivePlanning([
            [
                'signature_index' => 0,
                'byte_range' => [0, 10, 20, 30],
                'byte_range_valid' => true,
                'byte_range_error' => null,
            ],
        ], [
            [
                'signature_index' => 0,
                'contents_hex_length' => 8,
            ],
        ], 50);

        self::assertCount(1, $planning);
        self::assertSame(8, $planning[0]['planned_contents_hex_length']);
        self::assertSame(10, $planning[0]['reserved_container_length']);
        self::assertSame(20, $planning[0]['expected_second_part_offset']);
        self::assertTrue($planning[0]['matches_final_second_part_offset']);
        self::assertTrue($planning[0]['covers_eof']);
        self::assertSame(8, $planning[0]['observed_contents_hex_length']);
        self::assertTrue($planning[0]['matches_observed_contents_hex_length']);
    }

    public function test_map_signatures_to_revisions_finds_matching_boundary(): void
    {
        $inspector = new ByteRangeInspector();

        $mapping = $inspector->mapSignaturesToRevisions([
            ['index' => 0, 'startxref' => 100, 'eof_offset' => 120, 'revision_start' => 0, 'revision_end' => 49],
            ['index' => 1, 'startxref' => 200, 'eof_offset' => 220, 'revision_start' => 50, 'revision_end' => 99],
        ], [
            ['signature_index' => 0, 'expected_second_part_offset' => 20],
            ['signature_index' => 1, 'expected_second_part_offset' => 80],
        ]);

        self::assertCount(2, $mapping);
        self::assertSame(0, $mapping[0]['revision_index']);
        self::assertSame(1, $mapping[1]['revision_index']);
        self::assertTrue($mapping[0]['fits_revision_boundary']);
        self::assertTrue($mapping[1]['fits_revision_boundary']);
    }

    public function test_check_coverage_consistency_detects_progression_and_overlap(): void
    {
        $inspector = new ByteRangeInspector();

        $consistent = $inspector->checkCoverageConsistency([
            ['signature_index' => 0, 'byte_range' => [0, 10, 20, 30], 'byte_range_valid' => true, 'byte_range_error' => null],
            ['signature_index' => 1, 'byte_range' => [0, 15, 50, 20], 'byte_range_valid' => true, 'byte_range_error' => null],
        ]);
        self::assertTrue($consistent['applicable']);
        self::assertTrue($consistent['all_start_from_zero']);
        self::assertFalse($consistent['has_overlap']);
        self::assertTrue($consistent['coverage_progression_ok']);

        $overlap = $inspector->checkCoverageConsistency([
            ['signature_index' => 0, 'byte_range' => [0, 10, 20, 50], 'byte_range_valid' => true, 'byte_range_error' => null],
            ['signature_index' => 1, 'byte_range' => [0, 15, 30, 30], 'byte_range_valid' => true, 'byte_range_error' => null],
        ]);
        self::assertTrue($overlap['has_overlap']);
    }
}
