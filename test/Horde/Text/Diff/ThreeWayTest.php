<?php

declare(strict_types=1);

namespace Horde\Text\Diff;

use Horde_Text_Diff_ThreeWay;
use PHPUnit\Framework\TestCase;

/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/gpl GPL
 * @category   Horde
 * @package    Text_Diff
 * @subpackage UnitTests
 */
class ThreeWayTest extends TestCase
{
    protected array $_lines = [];

    public function setUp(): void
    {
        parent::setUp();

        for ($i = 1; $i <= 4; $i++) {
            $this->_lines[$i] = file(__DIR__ . '/fixtures/' . $i . '.txt');
        }
    }

    public function testChangesAddingUp(): void
    {
        $diff = new Horde_Text_Diff_ThreeWay($this->_lines[1], $this->_lines[2], $this->_lines[3]);
        $merge = <<<END_OF_MERGE
This line is the same.
This line is different in 2.txt
This line is the same.
This line is new in 3.txt
END_OF_MERGE;
        self::assertEquals($merge, implode("\n", $diff->mergedOutput('2.txt', '3.txt')));
    }

    public function testConflictingChanges(): void
    {
        $diff = new Horde_Text_Diff_ThreeWay($this->_lines[1], $this->_lines[2], $this->_lines[4]);
        $merge = <<<END_OF_MERGE
This line is the same.
<<<<<<< 2.txt
This line is different in 2.txt
=======
This line is different in 4.txt
>>>>>>> 4.txt
This line is the same.
END_OF_MERGE;
        self::assertEquals($merge, implode("\n", $diff->mergedOutput('2.txt', '4.txt')));
    }
}
