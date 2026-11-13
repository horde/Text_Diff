<?php

/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/gpl GPL
 * @category   Horde
 * @package    Text_Diff
 * @subpackage UnitTests
 */

namespace Horde\Text\Diff\Test;

use Horde\Text\Diff\ThreeWay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @requires PHP >= 8.1
 */
#[CoversClass(ThreeWay::class)]
class ThreeWayTest extends TestCase
{
    protected $_lines = [];
    protected $fixtureDir = '';

    public function setUp(): void
    {
        $this->fixtureDir =  dirname(__FILE__, 1) . '/fixtures/';
        for ($i = 1; $i <= 4; $i++) {
            $this->_lines[$i] = file($this->fixtureDir . $i . '.txt');
        }
    }

    public function testChangesAddingUp()
    {
        $diff = ThreeWay::fromFileLineArrays($this->_lines[1], $this->_lines[2], $this->_lines[3], 'NativeEngine');
        $merge = <<<END_OF_MERGE
            This line is the same.
            This line is different in 2.txt
            This line is the same.
            This line is new in 3.txt
            END_OF_MERGE;
        $this->assertEquals($merge, implode("\n", $diff->mergedOutput('2.txt', '3.txt')));
    }

    public function testConflictingChanges()
    {
        $diff = ThreeWay::fromFileLineArrays($this->_lines[1], $this->_lines[2], $this->_lines[4], 'NativeEngine');
        $merge = file_get_contents($this->fixtureDir . 'merge.txt');
        $this->assertEquals($merge, implode("\n", $diff->mergedOutput('2.txt', '4.txt')));
    }

    // -------------------------------------------------------------------------
    // Three way merge boundary cases
    // ThreeWayBaseOperation::merged() returns:
    //   - final1  when final1 === final2  (both sides identical change)
    //   - final2  when final1 === orig    (only theirs changed)
    //   - final1  when final2 === orig    (only mine changed)
    //   - false   otherwise              (conflict)
    // -------------------------------------------------------------------------

    /**
     * base = mine = theirs = 1.txt (no changes on either side).
     * All three way ops are ThreeWayCopyOperation -> merged() = orig lines.
     * No conflict markers must appear. Output equals the base content.
     */
    public function testBothSidesIdenticalToBase(): void
    {
        $diff   = ThreeWay::fromFileLineArrays(
            $this->_lines[1], // base
            $this->_lines[1], // mine  (same as base)
            $this->_lines[1], // theirs (same as base)
            'NativeEngine'
        );
        $merged = implode("\n", $diff->mergedOutput('1.txt', '1.txt'));

        $this->assertStringNotContainsString('<<<<<<<', $merged, 'No conflict markers expected');
        $this->assertStringNotContainsString('=======', $merged);
        $this->assertStringNotContainsString('>>>>>>>', $merged);

        // All original lines must appear in the merged output
        $this->assertStringContainsString('This line is the same.', $merged);
        $this->assertStringContainsString('This line is different in 1.txt', $merged);
    }

    /**
     * base = 1.txt, mine = 2.txt (changed line 2), theirs = 1.txt (unchanged).
     * ThreeWayBaseOperation::merged(): final2 === orig returns final1 (mine's version).
     * Clean merge. Result matches 2.txt content.
     */
    public function testOnlyMineSideChanged(): void
    {
        $diff   = ThreeWay::fromFileLineArrays(
            $this->_lines[1], // base
            $this->_lines[2], // mine  (changed line 2)
            $this->_lines[1], // theirs (unchanged from base)
            'NativeEngine'
        );
        $merged = implode("\n", $diff->mergedOutput('2.txt', '1.txt'));

        $this->assertStringNotContainsString('<<<<<<<', $merged, 'No conflict markers expected');
        $this->assertStringContainsString(
            'This line is different in 2.txt',
            $merged,
            "Mine's changed line must appear in the merge"
        );
        $this->assertStringNotContainsString(
            'This line is different in 1.txt',
            $merged,
            'Original (base) version of the changed line must not appear'
        );
    }

    /**
     * base = 1.txt, mine = 1.txt (unchanged), theirs = 2.txt (changed line 2).
     * ThreeWayBaseOperation::merged(): final1 === orig returns final2 (theirs's version).
     * Clean merge. Result matches 2.txt content.
     */
    public function testOnlyTheirsSideChanged(): void
    {
        $diff   = ThreeWay::fromFileLineArrays(
            $this->_lines[1], // base
            $this->_lines[1], // mine  (unchanged from base)
            $this->_lines[2], // theirs (changed line 2)
            'NativeEngine'
        );
        $merged = implode("\n", $diff->mergedOutput('1.txt', '2.txt'));

        $this->assertStringNotContainsString('<<<<<<<', $merged, 'No conflict markers expected');
        $this->assertStringContainsString(
            'This line is different in 2.txt',
            $merged,
            "Theirs's changed line must appear in the merge"
        );
        $this->assertStringNotContainsString(
            'This line is different in 1.txt',
            $merged,
            'Original (base) version of the changed line must not appear'
        );
    }

    /**
     * base = 1.txt, mine = 2.txt, theirs = 2.txt (both sides make the exact same change).
     * ThreeWayBaseOperation::merged(): final1 === final2 returns final1.
     * Identical changes from both sides must NOT produce a conflict.
     */
    public function testBothSidesIdenticalChange(): void
    {
        $diff   = ThreeWay::fromFileLineArrays(
            $this->_lines[1], // base
            $this->_lines[2], // mine  (changed line 2)
            $this->_lines[2], // theirs (identical change to mine)
            'NativeEngine'
        );
        $merged = implode("\n", $diff->mergedOutput('2.txt', '2.txt'));

        $this->assertStringNotContainsString(
            '<<<,<<<<',
            $merged,
            'Identical changes on both sides must not produce a conflict'
        );
        $this->assertStringContainsString(
            'This line is different in 2.txt',
            $merged,
            'The shared change must appear in the merged output'
        );
    }
}
