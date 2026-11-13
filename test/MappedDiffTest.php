<?php

/**
 * @author     Ralf Lang
 * @license    http://www.horde.org/licenses/gpl GPL
 * @category   Horde
 * @package    Text_Diff
 * @subpackage UnitTests
 *
 * Custom key / comparator mapping (MappedDiff)
 *
 * MappedDiff allows diffs to be computed against mapped (transformed) versions
 * of lines - e.g. strtolower() for case-insensitive diffs, trim() for
 * whitespace-ignoring diffs - while keeping the original (un-transformed)
 * content in the resulting ops.
 *
 * Peers:
 *   Perl   t/base.t - keyGen option tested on every function
 *   Python TestAutojunk - isjunk / autojunk parameter propagation
 *
 * !! KNOWN BUG (PHP 8+) !! - This must be fixed in production code.
 * MappedDiff::__construct() uses array subscript syntax ($this->edits[$i])
 * to iterate over the OperationList. OperationList does not implement
 * ArrayAccess.
 *
 * This causes a fatal error in PHP 8+:
 *   "Cannot use object of type Horde\Text\Diff\OperationList as array"
 */

namespace Horde\Text\Diff\Test;

use Horde\Text\Diff\ChangeOperation;
use Horde\Text\Diff\CopyOperation;
use Horde\Text\Diff\MappedDiff;
use Horde\Text\Diff\NativeEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @requires PHP >= 8.1
 */
#[CoversClass(MappedDiff::class)]
#[CoversClass(NativeEngine::class)]
class MappedDiffTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helper: build a MappedDiff from original line arrays and a mapping callable
    // -------------------------------------------------------------------------

    /**
     * Constructs a MappedDiff by:
     *   1. Mapping both line arrays with $mapFn.
     *   2. Running NativeEngine on the mapped versions to get the ops.
     *   3. Creating MappedDiff($ops, $from, $to) so that ops carry original content.
     *
     * @param string[] $from   Original lines (un-mapped)
     * @param string[] $to     Revised lines (un-mapped)
     * @param callable $mapFn  Mapping function, e.g. 'strtolower', 'trim'
     */
    private function buildMappedDiff(array $from, array $to, callable $mapFn): MappedDiff
    {
        $mappedFrom = array_map($mapFn, $from);
        $mappedTo   = array_map($mapFn, $to);

        $engine = new NativeEngine($mappedFrom, $mappedTo);
        $edits  = $engine->diff();

        return new MappedDiff($edits, $from, $to);
    }

    // -------------------------------------------------------------------------
    // Custom key / comparator mapping
    // -------------------------------------------------------------------------

    /**
     * Lines that differ only in character case must produce a CopyOperation
     * (treated as equal) when mapped with strtolower().
     *
     * Input:
     *   from: ["Hello World\n"]
     *   to:   ["HELLO WORLD\n"]
     *   map:  strtolower / both become ["hello world\n"]
     *
     * Expected: 1 CopyOperation whose orig holds the original-case from-line
     *           and whose final holds the original-case to-line.
     */
    public function testCaseInsensitiveDiff(): void
    {
        $from = ["Hello World\n"];
        $to   = ["HELLO WORLD\n"];

        $diff = $this->buildMappedDiff($from, $to, 'strtolower');
        $ops  = $diff->getDiff()->toArray();

        $this->assertCount(
            1,
            $ops,
            'A single-line case-only change should produce exactly one op'
        );
        $this->assertInstanceOf(
            CopyOperation::class,
            $ops[0],
            'Lines differing only in case should be treated as equal (CopyOperation)'
        );

        // Original (un-mapped) content must be preserved in the op
        $this->assertSame(
            "Hello World\n",
            $ops[0]->orig[0],
            'orig should contain the original from-line, not the mapped version'
        );
        $this->assertSame(
            "HELLO WORLD\n",
            $ops[0]->final[0],
            'final should contain the original to-line, not the mapped version'
        );
    }

    /**
     * Lines that differ only in surrounding whitespace must produce a
     * CopyOperation when mapped with trim().
     *
     * Input:
     *   from: ["  This line is the same.  \n"]
     *   to:   ["This line is the same.\n"]
     *   map:  trim, both become ["This line is the same."]
     *
     * Expected: 1 CopyOperation with original (un-trimmed) content preserved.
     */
    public function testWhitespaceIgnoringDiff(): void
    {
        $from = ["  This line is the same.  \n"];
        $to   = ["This line is the same.\n"];

        $diff = $this->buildMappedDiff($from, $to, 'trim');
        $ops  = $diff->getDiff()->toArray();

        $this->assertCount(
            1,
            $ops,
            'A single-line whitespace-only change should produce exactly one op'
        );
        $this->assertInstanceOf(
            CopyOperation::class,
            $ops[0],
            'Lines differing only in whitespace should be treated as equal (CopyOperation)'
        );

        $this->assertSame(
            "  This line is the same.  \n",
            $ops[0]->orig[0],
            'orig should retain leading/trailing whitespace from the original from-line'
        );
        $this->assertSame(
            "This line is the same.\n",
            $ops[0]->final[0],
            'final should retain the original to-line without extra whitespace'
        );
    }

    /**
     * A genuine content change (different even after mapping) must still
     * produce a ChangeOperation, and the op must carry the un-mapped originals.
     *
     * Input:
     *   from: ["Hello World\n"]
     *   to:   ["Goodbye World\n"]
     *
     * Expected: 1 ChangeOperation with original-case content in orig and final.
     */
    public function testMappedDiffDetectsRealChanges(): void
    {
        $from = ["Hello World\n"];
        $to   = ["Goodbye World\n"];

        $diff = $this->buildMappedDiff($from, $to, 'strtolower');
        $ops  = $diff->getDiff()->toArray();

        $this->assertCount(
            1,
            $ops,
            'A genuine single-line change should produce exactly one op'
        );
        $this->assertInstanceOf(
            ChangeOperation::class,
            $ops[0],
            'A line whose mapped value also differs should produce a ChangeOperation'
        );

        $this->assertSame(
            "Hello World\n",
            $ops[0]->orig[0],
            'orig should contain the original from-line'
        );
        $this->assertSame(
            "Goodbye World\n",
            $ops[0]->final[0],
            'final should contain the original to-line'
        );
    }

    /**
     * Mixed scenario: A multi-line sequence where one pair of lines differs only
     * in case (should merge as equal) and another pair has a genuine change.
     *
     * Input:
     *   from: ["Same Line\n",    "Old Content\n"]
     *   to:   ["SAME LINE\n",   "New Content\n"]
     *   map:  strtolower
     *
     * Mapped:
     *   from: ["same line\n",   "old content\n"]
     *   to:   ["same line\n",   "new content\n"]
     *
     * Expected ops (in order):
     *   [0] CopyOperation   orig="Same Line\n"  final="SAME LINE\n"
     *   [1] ChangeOperation orig="Old Content\n" final="New Content\n"
     */
    public function testMixedCaseInsensitiveAndRealChange(): void
    {
        $from = ["Same Line\n",  "Old Content\n"];
        $to   = ["SAME LINE\n", "New Content\n"];

        $diff = $this->buildMappedDiff($from, $to, 'strtolower');
        $ops  = $diff->getDiff()->toArray();

        $this->assertCount(
            2,
            $ops,
            'Should produce two ops: one copy (case-only) and one change (genuine)'
        );

        $this->assertInstanceOf(
            CopyOperation::class,
            $ops[0],
            'First op: case-only line should be CopyOperation'
        );
        $this->assertSame("Same Line\n", $ops[0]->orig[0]);
        $this->assertSame("SAME LINE\n", $ops[0]->final[0]);

        $this->assertInstanceOf(
            ChangeOperation::class,
            $ops[1],
            'Second op: genuinely different line should be ChangeOperation'
        );
        $this->assertSame("Old Content\n", $ops[1]->orig[0]);
        $this->assertSame("New Content\n", $ops[1]->final[0]);
    }
}
