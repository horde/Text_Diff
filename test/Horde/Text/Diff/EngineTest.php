<?php

declare(strict_types=1);

namespace Horde\Text\Diff;

use Horde_Text_Diff;
use Horde_Text_Diff_Exception;
use PHPUnit\Framework\TestCase;

/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/gpl GPL
 * @category   Horde
 * @package    Text_Diff
 * @subpackage UnitTests
 */
class EngineTest extends TestCase
{
    protected array $_lines = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->_lines = [
            1 => file(__DIR__ . '/fixtures/1.txt'),
            2 => file(__DIR__ . '/fixtures/2.txt')];
    }

    protected function _testDiff(Horde_Text_Diff $diff): void
    {
        $edits = $diff->getDiff();
        self::assertCount(3, $edits);
        self::assertInstanceOf('Horde_Text_Diff_Op_Copy', $edits[0]);
        self::assertInstanceOf('Horde_Text_Diff_Op_Change', $edits[1]);
        self::assertInstanceOf('Horde_Text_Diff_Op_Copy', $edits[2]);
        self::assertEquals('This line is the same.', $edits[0]->orig[0]);
        self::assertEquals('This line is the same.', $edits[0]->final[0]);
        self::assertEquals('This line is different in 1.txt', $edits[1]->orig[0]);
        self::assertEquals('This line is different in 2.txt', $edits[1]->final[0]);
        self::assertEquals('This line is the same.', $edits[2]->orig[0]);
        self::assertEquals('This line is the same.', $edits[2]->final[0]);
    }

    public function testNativeEngine(): void
    {
        $diff = new Horde_Text_Diff('Native', [$this->_lines[1], $this->_lines[2]]);
        $this->_testDiff($diff);
    }

    public function testShellEngine(): void
    {
        if (!exec('which diff')) {
            self::markTestSkipped('diff executable not found');
        }
        $diff = new Horde_Text_Diff('Shell', [$this->_lines[1], $this->_lines[2]]);
        $this->_testDiff($diff);
    }

    public function testStringEngine(): void
    {
        $patch = file_get_contents(__DIR__ . '/fixtures/unified.patch');
        $diff = new Horde_Text_Diff('String', [$patch]);
        $this->_testDiff($diff);

        $patch = file_get_contents(__DIR__ . '/fixtures/unified2.patch');
        try {
            $diff = new Horde_Text_Diff('String', [$patch]);
            self::fail('Horde_Text_Diff_Exception expected');
        } catch (Horde_Text_Diff_Exception $e) {
        }
        $diff = new Horde_Text_Diff('String', [$patch, 'unified']);
        $edits = $diff->getDiff();
        self::assertCount(1, $edits);
        self::assertInstanceOf('Horde_Text_Diff_Op_Change', $edits[0]);
        self::assertEquals('For the first time in U.S. history number of private contractors and troops are equal', $edits[0]->orig[0]);
        self::assertEquals('Number of private contractors and troops are equal for first time in U.S. history', $edits[0]->final[0]);

        $patch = file_get_contents(__DIR__ . '/fixtures/context.patch');
        $diff = new Horde_Text_Diff('String', [$patch]);
        $this->_testDiff($diff);
    }

    public function testXdiffEngine(): void
    {
        $this->expectNotToPerformAssertions();

        try {
            $diff = new Horde_Text_Diff('Xdiff', [$this->_lines[1], $this->_lines[2]]);
            $this->_testDiff($diff);
        } catch (Horde_Text_Diff_Exception $e) {
            if (extension_loaded('xdiff')) {
                throw $e;
            }
        }
    }
}
