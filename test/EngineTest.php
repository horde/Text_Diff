<?php

/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/gpl GPL
 * @category   Horde
 * @package    Text_Diff
 * @subpackage UnitTests
 */

namespace Horde\Text\Diff\Test;

use Horde\Text\Diff\AddOperation;
use Horde\Text\Diff\ChangeOperation;
use Horde\Text\Diff\CopyOperation;
use Horde\Text\Diff\DeleteOperation;
use Horde\Text\Diff\Diff;
use Horde\Text\Diff\DiffEngineFactory;
use Horde\Text\Diff\Exception;
use Horde\Text\Diff\NativeEngine;
use Horde\Text\Diff\StringEngine;
use Horde\Text\Diff\XdiffEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @requires PHP >= 8.1
 */
#[CoversClass(AddOperation::class)]
#[CoversClass(ChangeOperation::class)]
#[CoversClass(DeleteOperation::class)]
#[CoversClass(Diff::class)]
#[CoversClass(DiffEngineFactory::class)]
#[CoversClass(NativeEngine::class)]
#[CoversClass(StringEngine::class)]
#[CoversClass(XdiffEngine::class)]
class EngineTest extends TestCase
{
    protected $_lines = [];
    protected $fixtureDir = '';

    public function setUp(): void
    {
        $this->fixtureDir = dirname(__FILE__, 1) . '/fixtures/';
        $this->_lines = [
            1 => file($this->fixtureDir . '1.txt'),
            2 => file($this->fixtureDir . '2.txt')];
    }

    protected function _testDiff($diff)
    {
        $edits = $diff->getDiff()->toArray();
        $this->assertEquals(3, count($edits));
        $this->assertInstanceof(CopyOperation::class, $edits[0]);
        $this->assertInstanceof(ChangeOperation::class, $edits[1]);
        $this->assertInstanceof(CopyOperation::class, $edits[2]);
        $this->assertEquals('This line is the same.', $edits[0]->orig[0]);
        $this->assertEquals('This line is the same.', $edits[0]->final[0]);
        $this->assertEquals('This line is different in 1.txt', $edits[1]->orig[0]);
        $this->assertEquals('This line is different in 2.txt', $edits[1]->final[0]);
        $this->assertEquals('This line is the same.', $edits[2]->orig[0]);
        $this->assertEquals('This line is the same.', $edits[2]->final[0]);
    }

    public function testNativeEngine()
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], 'NativeEngine');
        $this->_testDiff($diff);
    }

    public function testShellEngine()
    {
        if (!exec('which diff')) {
            $this->markTestSkipped('diff executable not found');
        }
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], 'ShellEngine');
        $this->_testDiff($diff);
    }

    public function testStringEngine()
    {
        $patch = file_get_contents($this->fixtureDir . 'unified.patch');
        $diff = Diff::fromString($patch, StringEngine::class);
        $this->_testDiff($diff);

        $patch = file_get_contents($this->fixtureDir . 'unified2.patch');
        $this->expectException(Exception::class);
        $diff = Diff::fromString($patch, StringEngine::class);
        $diff = Diff::fromString($patch, StringEngine::class, ['format' => 'unified']);
        $edits = $diff->getDiff();
        $this->assertEquals(1, count($edits));
        $this->assertInstanceof(ChangeOperation::class, $edits[0]);
        $this->assertEquals('For the first time in U.S. history number of private contractors and troops are equal', $edits[0]->orig[0]);
        $this->assertEquals('Number of private contractors and troops are equal for first time in U.S. history', $edits[0]->final[0]);

        $patch = file_get_contents($this->fixtureDir . 'context.patch');
        $diff = Diff::fromString($patch);
        $this->_testDiff($diff);
    }

    public function testXdiffEngine()
    {
        $this->markTestIncomplete("This test is incomplete because Op_Change is not implemented with Xdiff");
        $this->expectException(Exception::class);
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], XdiffEngine::class);
        $this->_testDiff($diff);
    }

    // -------------------------------------------------------------------------
    // Empty-sequence edge cases
    // -------------------------------------------------------------------------

    public function testDiffOnEmptyOriginal(): void
    {
        $diff  = Diff::fromFileLineArrays([], $this->_lines[1], NativeEngine::class);
        $edits = $diff->getDiff()->toArray();

        $this->assertCount(1, $edits);
        $this->assertInstanceOf(AddOperation::class, $edits[0]);
        // AddOperation sets orig = false
        $this->assertFalse($edits[0]->orig);
        // NativeEngine strips trailing newlines from op content.
        $expected = $this->_lines[1];
        array_walk($expected, [Diff::class, 'trimNewlines']);
        $this->assertEquals($expected, $edits[0]->final);
    }

    public function testDiffOnEmptyRevised(): void
    {
        $diff  = Diff::fromFileLineArrays($this->_lines[1], [], NativeEngine::class);
        $edits = $diff->getDiff()->toArray();

        $this->assertCount(1, $edits);
        $this->assertInstanceOf(DeleteOperation::class, $edits[0]);
        // NativeEngine strips trailing newlines from op content.
        $expected = $this->_lines[1];
        array_walk($expected, [Diff::class, 'trimNewlines']);
        $this->assertEquals($expected, $edits[0]->orig);
        // DeleteOperation sets final = false
        $this->assertFalse($edits[0]->final);
    }

    public function testDiffOnBothEmpty(): void
    {
        $diff  = Diff::fromFileLineArrays([], [], NativeEngine::class);
        $edits = $diff->getDiff()->toArray();

        $this->assertCount(0, $edits);
    }

    // -------------------------------------------------------------------------
    // Identical sequences (engine-level assertion)
    // -------------------------------------------------------------------------

    public function testDiffOnIdenticalLines(): void
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[1], NativeEngine::class);

        foreach ($diff->getDiff() as $op) {
            $this->assertInstanceOf(
                CopyOperation::class,
                $op,
                'Diff of identical sequences must only produce CopyOperation ops'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Input validation / exception coverage
    // -------------------------------------------------------------------------

    /**
     * StringEngine::diff() throws when mode is not 'context', 'unified', or 'autodetect'.
     * Message: 'Type of diff is unsupported'
     */
    public function testStringEngineRejectsUnsupportedMode(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Type of diff is unsupported');

        Diff::fromString(
            file_get_contents($this->fixtureDir . 'unified.patch'),
            StringEngine::class,
            ['mode' => 'bogus']
        );
    }

    /**
     * An empty string has neither '***' nor '---' and autodetect cannot
     * determine the format.
     * Message: 'Type of diff could not be detected'
     */
    public function testStringEngineRejectsEmptyString(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Type of diff could not be detected');

        Diff::fromString('', StringEngine::class);
    }

    /**
     * unified2.patch starts directly with '@@ -1 +1 @@' no '---' or '***'
     * header lines. Autodetect cannot determine the format.
     *
     * Note: the existing testStringEngine() already calls expectException() for
     * this fixture but then continues with unreachable post-exception assertions.
     * This test isolates that scenario correctly.
     */
    public function testStringEngineRejectsHeaderlessUnifiedPatch(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Type of diff could not be detected');

        Diff::fromString(
            file_get_contents($this->fixtureDir . 'unified2.patch'),
            StringEngine::class
        );
    }

    // -------------------------------------------------------------------------
    // Patch round-trip (parse to reconstruct to verify)
    // -------------------------------------------------------------------------

    /**
     * Parse a unified patch, reconstruct the revised file by walking the ops,
     * and verify the result matches the known content of 2.txt.
     *
     * Reconstruction rules:
     *   CopyOperation  append orig lines (unchanged, present in revised)
     *   AddOperation   append final lines (new in revised)
     *   ChangeOperation append final lines (replacement in revised)
     *   DeleteOperation skip (removed from original / absent in revised)
     *
     * StringEngine strips newlines when splitting on line boundaries, so the
     * expected values are compared without trailing newline characters.
     */
    public function testUnifiedPatchRoundtrip(): void
    {
        $patch = file_get_contents($this->fixtureDir . 'unified.patch');
        $diff  = Diff::fromString($patch, StringEngine::class);

        $revised = [];
        foreach ($diff->getDiff() as $op) {
            if ($op instanceof CopyOperation) {
                foreach ($op->orig as $line) {
                    $revised[] = $line;
                }
            } elseif ($op instanceof AddOperation) {
                foreach ($op->final as $line) {
                    $revised[] = $line;
                }
            } elseif ($op instanceof ChangeOperation) {
                foreach ($op->final as $line) {
                    $revised[] = $line;
                }
            }
            // DeleteOperation: intentionally skipped
        }

        $this->assertEquals(
            [
                'This line is the same.',
                'This line is different in 2.txt',
                'This line is the same.',
            ],
            $revised
        );
    }

    /**
     * Same round-trip verification using a context-format patch.
     * The expected reconstructed content is identical to testUnifiedPatchRoundtrip
     * because both patches describe the same 1.txt to 2.txt transformation.
     */
    public function testContextPatchRoundtrip(): void
    {
        $patch = file_get_contents($this->fixtureDir . 'context.patch');
        $diff  = Diff::fromString($patch, StringEngine::class);

        $revised = [];
        foreach ($diff->getDiff() as $op) {
            if ($op instanceof CopyOperation) {
                foreach ($op->orig as $line) {
                    $revised[] = $line;
                }
            } elseif ($op instanceof AddOperation) {
                foreach ($op->final as $line) {
                    $revised[] = $line;
                }
            } elseif ($op instanceof ChangeOperation) {
                foreach ($op->final as $line) {
                    $revised[] = $line;
                }
            }
        }

        $this->assertEquals(
            [
                'This line is the same.',
                'This line is different in 2.txt',
                'This line is the same.',
            ],
            $revised
        );
    }

    // -------------------------------------------------------------------------
    // DiffEngineFactory auto-selection
    // -------------------------------------------------------------------------

    /**
     * With 'auto' and the xdiff extension absent, DiffEngineFactory falls back
     * to NativeEngine. The result must be structurally identical to an explicit
     * NativeEngine call.
     *
     * Source: DiffEngineFactory::fromFileLineArrays(), line 37:
     *   extension_loaded('xdiff') ? XdiffEngine::class : NativeEngine::class
     */
    public function testAutoEngineProducesSameResultAsNativeWhenXdiffAbsent(): void
    {
        if (extension_loaded('xdiff')) {
            $this->markTestSkipped('xdiff extension is loaded; auto selects XdiffEngine instead of NativeEngine');
        }

        $auto   = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], 'auto');
        $native = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], NativeEngine::class);

        $autoEdits   = $auto->getDiff()->toArray();
        $nativeEdits = $native->getDiff()->toArray();

        $this->assertCount(
            count($nativeEdits),
            $autoEdits,
            'auto and NativeEngine should produce the same number of ops'
        );

        foreach ($autoEdits as $i => $op) {
            $this->assertInstanceOf(
                get_class($nativeEdits[$i]),
                $op,
                "Op $i: auto result class should match NativeEngine result class"
            );
            $this->assertEquals(
                $nativeEdits[$i]->orig,
                $op->orig,
                "Op $i: orig content should match"
            );
            $this->assertEquals(
                $nativeEdits[$i]->final,
                $op->final,
                "Op $i: final content should match"
            );
        }
    }

    /**
     * With 'auto' and the xdiff extension present the DiffEngineFactory selects
     * XdiffEngine. XdiffEngine does not implement ChangeOperation (see
     * testXdiffEngine which is marked incomplete).
     */
    public function testAutoEngineUsesXdiffWhenAvailable(): void
    {
        if (!extension_loaded('xdiff')) {
            $this->markTestSkipped('xdiff extension is not loaded');
        }

        // Pure addition: No ChangeOperation needed and XdiffEngine can handle this
        $from = ['line one', 'line two'];
        $to   = ['line one', 'line two', 'line three'];

        $diff  = Diff::fromFileLineArrays($from, $to, 'auto');
        $edits = $diff->getDiff()->toArray();

        $this->assertNotEmpty($edits, 'auto engine should produce at least one op');

        // At minimum a copy of the two shared lines and an add of the new one
        $hasAdd = false;
        foreach ($edits as $op) {
            if ($op instanceof AddOperation) {
                $hasAdd = true;
            }
        }
        $this->assertTrue($hasAdd, 'auto engine (XdiffEngine) should produce an AddOperation for the appended line');
    }

    // -------------------------------------------------------------------------
    // XdiffEngine partial coverage (Option B)
    //
    // testXdiffEngine() is permanently markTestIncomplete because XdiffEngine
    // does not produce ChangeOperation.  These tests cover the insert-only
    // and delete-only paths, which XdiffEngine handles correctly.
    //
    // XdiffEngine::diff() (src/XdiffEngine.php lines 33-35) throws
    // Horde\Text\Diff\Exception when the xdiff PECL extension is absent.
    // Both tests are skipped in that case.
    // -------------------------------------------------------------------------

    /**
     * XdiffEngine on a pure insertion (from = [], to = lines) must produce
     * only AddOperation ops.
     *
     * XdiffEngine uses xdiff_string_diff() and parses unified-format output.
     * There are no "-" or " " lines when from is empty.
     */
    public function testXdiffEngineInsertOnly(): void
    {
        if (!extension_loaded('xdiff')) {
            $this->markTestSkipped('xdiff extension is not loaded');
        }

        $from = [];
        $to   = ['alpha', 'beta', 'gamma'];

        $diff  = Diff::fromFileLineArrays($from, $to, XdiffEngine::class);
        $edits = $diff->getDiff()->toArray();

        $this->assertNotEmpty(
            $edits,
            'Insert-only diff must produce at least one op'
        );

        foreach ($edits as $op) {
            $this->assertInstanceOf(
                AddOperation::class,
                $op,
                'Insert-only diff via XdiffEngine must produce only AddOperation ops'
            );
        }
    }

    /**
     * XdiffEngine on a pure deletion (from = lines, to = []) must produce
     * only DeleteOperation ops.
     *
     * "-" prefixed lines in the xdiff output -> DeleteOperation([substr($line,1)]).
     */
    public function testXdiffEngineDeleteOnly(): void
    {
        if (!extension_loaded('xdiff')) {
            $this->markTestSkipped('xdiff extension is not loaded');
        }

        $from = ['alpha', 'beta', 'gamma'];
        $to   = [];

        $diff  = Diff::fromFileLineArrays($from, $to, XdiffEngine::class);
        $edits = $diff->getDiff()->toArray();

        $this->assertNotEmpty(
            $edits,
            'Delete-only diff must produce at least one op'
        );

        foreach ($edits as $op) {
            $this->assertInstanceOf(
                DeleteOperation::class,
                $op,
                'Delete-only diff via XdiffEngine must produce only DeleteOperation ops'
            );
        }
    }
}
