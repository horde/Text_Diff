<?php

/**
 * @author     Ralf Lang
 * @license    http://www.horde.org/licenses/gpl GPL
 * @category   Horde
 * @package    Text_Diff
 * @subpackage UnitTests
 *
 */

namespace Horde\Text\Diff\Test;

use Horde\Text\Diff\AddOperation;
use Horde\Text\Diff\ChangeOperation;
use Horde\Text\Diff\CopyOperation;
use Horde\Text\Diff\DeleteOperation;
use Horde\Text\Diff\Diff;
use Horde\Text\Diff\NativeEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @requires PHP >= 8.1
 */
#[CoversClass(Diff::class)]
#[CoversClass(NativeEngine::class)]
class DiffApiTest extends TestCase
{
    protected array $_lines = [];
    protected string $fixtureDir = '';

    public function setUp(): void
    {
        $this->fixtureDir = dirname(__FILE__, 1) . '/fixtures/';
        $this->_lines = [
            1 => file($this->fixtureDir . '1.txt'),
            2 => file($this->fixtureDir . '2.txt'),
        ];
    }

    // -------------------------------------------------------------------------
    // Identical sequences / no-difference detection
    //
    // Peers:
    //   Perl   t/base.t  diff() on identical arrays returns empty hunk list
    //   Python TestSFbugs::test_comparing_empty_lists
    //   Java   DiffRowGeneratorTest::testGeneratorUnchanged
    // -------------------------------------------------------------------------

    /**
     * Diff::isEmpty() must return true when both input sequences are identical.
     *
     * Fixture: test/fixtures/1.txt diffed against itself.
     * Expected: every op is a CopyOperation so that  isEmpty() returns true.
     */
    public function testIsEmptyReturnsTrueForIdenticalInput(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[1], NativeEngine::class);

        $this->assertTrue(
            $diff->isEmpty(),
            'isEmpty() should return true when original and revised sequences are identical'
        );
    }

    /**
     * Diff::isEmpty() must return false when there is at least one non-copy op.
     *
     * Fixture: test/fixtures/1.txt vs test/fixtures/2.txt (line 2 differs).
     * Expected: the diff contains a ChangeOperation so that  isEmpty() returns false.
     */
    public function testIsEmptyReturnsFalseForDifferentInput(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], NativeEngine::class);

        $this->assertFalse(
            $diff->isEmpty(),
            'isEmpty() should return false when original and revised sequences differ'
        );
    }

    // -------------------------------------------------------------------------
    // LCS length computation
    //
    // Peers:
    //   Perl   t/base.t  LCS_length() on identical, empty, standard example
    //   Python SequenceMatcher  internal LCS length assertions
    // -------------------------------------------------------------------------

    /**
     * lcs() must equal the full line count when both sequences are identical.
     *
     * Fixture: test/fixtures/1.txt (3 lines) diffed against itself.
     * Expected: 3 copy lines so that lcs() == 3.
     */
    public function testLcsOnIdenticalLines(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[1], NativeEngine::class);

        $this->assertSame(
            3,
            $diff->lcs(),
            'lcs() should equal the total line count for identical sequences'
        );
    }

    /**
     * lcs() must return 0 when the two sequences share no common elements.
     *
     * Input: ['alpha', 'beta', 'gamma'] vs ['one', 'two', 'three']
     * Expected: no CopyOperations so that lcs() == 0.
     */
    public function testLcsOnCompletelyDifferentLines(): void
    {
        $from = ['alpha', 'beta', 'gamma'];
        $to   = ['one',   'two',  'three'];

        $diff = Diff::fromFileLineArrays($from, $to, NativeEngine::class);

        $this->assertSame(
            0,
            $diff->lcs(),
            'lcs() should return 0 when sequences share no common elements'
        );
    }

    /**
     * lcs() must return the exact count of lines shared between the two sequences.
     *
     * Fixture: test/fixtures/1.txt vs test/fixtures/2.txt.
     *   1.txt: ["This line is the same.\r\n",
     *            "This line is different in 1.txt\r\n",
     *            "This line is the same.\r\n"]
     *   2.txt: ["This line is the same.\r\n",
     *            "This line is different in 2.txt\r\n",
     *            "This line is the same.\r\n"]
     *
     */
    public function testLcsOnPartialMatch(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], NativeEngine::class);

        $this->assertSame(
            2,
            $diff->lcs(),
            'lcs() should return 2: lines 1 and 3 are common, line 2 differs'
        );
    }

    // -------------------------------------------------------------------------
    // reverse() iterates over $this->edits and calls $edit->reverse() on each:
    //   CopyOperation::reverse()   so that  CopyOperation($final, $orig)  (swaps sides)
    //   AddOperation::reverse()    so that  DeleteOperation($final)
    //   DeleteOperation::reverse() so that  AddOperation($orig)
    //   ChangeOperation::reverse() so that  ChangeOperation($final, $orig) (swaps sides)
    // -------------------------------------------------------------------------

    /**
     * reverse() must swap orig and final across all ops.
     * After reversal: getOriginal() == original getFinal(), and vice versa.
     *
     * Note: NativeEngine strips trailing newlines from the content stored in
     * ops, so getOriginal()/getFinal() never round-trip the raw file() lines
     * verbatim. The invariant under test is the swap, so we compare the
     * reversed diff against the original diff's (already-stripped) output.
     *
     * Fixture: 1.txt vs 2.txt.
     */
    public function testReverseSwapsOrigAndFinal(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], NativeEngine::class);
        $rev  = $diff->reverse();

        $this->assertEquals(
            $diff->getFinal(),
            $rev->getOriginal(),
            'Reversed diff: getOriginal() must equal the original getFinal()'
        );
        $this->assertEquals(
            $diff->getOriginal(),
            $rev->getFinal(),
            'Reversed diff: getFinal() must equal the original getOriginal()'
        );
    }

    /**
     * Reversing a diff twice must produce the same ops as the original.
     * reverse(reverse($diff)).getOriginal() === $diff.getOriginal()
     */
    public function testReverseOfReverseIsIdentity(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], NativeEngine::class);

        $this->assertEquals(
            $diff->getOriginal(),
            $diff->reverse()->reverse()->getOriginal(),
            'Double-reverse must restore the original sequence'
        );
        $this->assertEquals(
            $diff->getFinal(),
            $diff->reverse()->reverse()->getFinal(),
            'Double-reverse must restore the revised sequence'
        );
    }

    /**
     * Reversing a diff of identical lines must still produce only CopyOperations.
     * CopyOperation::reverse() returns CopyOperation($final, $orig) with swapped sides,
     * but since orig === final for copy ops, all ops remain CopyOperation.
     */
    public function testReverseOnCopyOpOnly(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[1], NativeEngine::class);
        $rev  = $diff->reverse();

        foreach ($rev->getDiff() as $op) {
            $this->assertInstanceOf(
                CopyOperation::class,
                $op,
                'Reversing a no-change diff must still produce only CopyOperations'
            );
        }
    }

    // -------------------------------------------------------------------------
    // countAddedLines() sums nfinal() of AddOperation and ChangeOperation ops.
    // countDeletedLines() sums norig() of DeleteOperation and ChangeOperation ops.
    // -------------------------------------------------------------------------

    /**
     * Fixture: 1.txt vs 2.txt  1 ChangeOperation with 1 final line.
     * Expected: countAddedLines() == 1
     */
    public function testCountAddedLines(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], NativeEngine::class);

        $this->assertSame(
            1,
            $diff->countAddedLines(),
            'countAddedLines() should return 1 for a single-line change (1 final line)'
        );
    }

    /**
     * Fixture: 1.txt vs 2.txt  1 ChangeOperation with 1 orig line.
     * Expected: countDeletedLines() == 1
     */
    public function testCountDeletedLines(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], NativeEngine::class);

        $this->assertSame(
            1,
            $diff->countDeletedLines(),
            'countDeletedLines() should return 1 for a single-line change (1 orig line)'
        );
    }

    /**
     * Identical input: no AddOperation or ChangeOperation ops.
     * Expected: countAddedLines() == 0
     */
    public function testCountAddedLinesZeroForIdentical(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[1], NativeEngine::class);

        $this->assertSame(
            0,
            $diff->countAddedLines(),
            'countAddedLines() should return 0 when sequences are identical'
        );
    }

    /**
     * Identical input: no DeleteOperation or ChangeOperation ops.
     * Expected: countDeletedLines() == 0
     */
    public function testCountDeletedLinesZeroForIdentical(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[1], NativeEngine::class);

        $this->assertSame(
            0,
            $diff->countDeletedLines(),
            'countDeletedLines() should return 0 when sequences are identical'
        );
    }

    /**
     * For a pure change (1 line replaced by 1 line):
     * countAddedLines() === countDeletedLines() (symmetry of the rename).
     */
    public function testCountLinesSymmetryForPureChange(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], NativeEngine::class);

        $this->assertSame(
            $diff->countDeletedLines(),
            $diff->countAddedLines(),
            'For a pure line-replace, added and deleted counts must be equal'
        );
    }

    // -------------------------------------------------------------------------
    // Diff::getOriginal() and Diff::getFinal()
    //
    // getOriginal() collects orig arrays from CopyOperation, ChangeOperation,
    // and DeleteOperation (all ops where $edit->orig is truthy).
    // getFinal() collects final arrays from CopyOperation, AddOperation,
    // and ChangeOperation.
    // -------------------------------------------------------------------------

    /**
     * getOriginal() must reconstruct the $fromLines passed to the engine.
     *
     * Fixture: 1.txt vs 2.txt via NativeEngine.
     * Note: NativeEngine strips trailing newlines from op content, so the
     * reconstruction equals the newline-trimmed input, not the raw file() lines.
     */
    public function testGetOriginalReconstructsFromLines(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], NativeEngine::class);

        $expected = $this->_lines[1];
        array_walk($expected, [Diff::class, 'trimNewlines']);

        $this->assertEquals(
            $expected,
            $diff->getOriginal(),
            'getOriginal() must reconstruct the original line array (newlines trimmed)'
        );
    }

    /**
     * getFinal() must reconstruct the $toLines passed to the engine.
     *
     * Fixture: 1.txt vs 2.txt via NativeEngine.
     * Note: NativeEngine strips trailing newlines from op content, so the
     * reconstruction equals the newline-trimmed input, not the raw file() lines.
     */
    public function testGetFinalReconstructsToLines(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], NativeEngine::class);

        $expected = $this->_lines[2];
        array_walk($expected, [Diff::class, 'trimNewlines']);

        $this->assertEquals(
            $expected,
            $diff->getFinal(),
            'getFinal() must reconstruct the revised line array (newlines trimmed)'
        );
    }

    /**
     * When original and revised are identical, both getOriginal() and getFinal()
     * must return the same array (all ops are CopyOperation; both sides equal).
     */
    public function testGetOriginalAndFinalOnIdenticalInput(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[1], NativeEngine::class);

        $this->assertEquals(
            $diff->getOriginal(),
            $diff->getFinal(),
            'For identical input, getOriginal() and getFinal() must return equal arrays'
        );
    }
}
