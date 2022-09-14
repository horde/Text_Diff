<?php
/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/gpl GPL
 * @category   Horde
 * @package    Text_Diff
 * @subpackage UnitTests
 */
namespace Horde\Text\Diff\Test;

use Horde\Text\Diff\ChangeOperation;
use Horde\Text\Diff\CopyOperation;
use PHPUnit\Framework\TestCase;
use Horde\Text\Diff\Diff;
use Horde\Text\Diff\Exception;
use Horde\Text\Diff\StringEngine;
use Horde\Text\Diff\XdiffEngine;
use Horde\Text\Diff\NativeEngine;


class EngineTest extends TestCase
{
    protected $_lines = array();
    protected $fixtureDir = '';

    public function setUp(): void
    {
        $this->fixtureDir = dirname(__FILE__, 1) . '/fixtures/';
        $this->_lines = array(
            1 => file($this->fixtureDir . '1.txt'),
            2 => file($this->fixtureDir . '2.txt'));
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
        $this->expectException(Exception::class);
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], XdiffEngine::class);
        $this->_testDiff($diff);
    }
}
