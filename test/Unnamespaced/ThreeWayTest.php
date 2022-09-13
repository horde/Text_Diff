<?php
/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/gpl GPL
 * @category   Horde
 * @package    Text_Diff
 * @subpackage UnitTests
 */
namespace Horde\Text\Diff\Test;
use Horde\Test\TestCase as TestCase;
use \Horde_Text_Diff_ThreeWay;

class ThreeWayTest extends TestCase
{
    protected $_lines = array();

    public function setUp(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->_lines[$i] = file(__DIR__ . '/fixtures/' . $i . '.txt');
        }
    }

    public function testChangesAddingUp()
    {
        $diff = new Horde_Text_Diff_ThreeWay($this->_lines[1], $this->_lines[2], $this->_lines[3]);
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
        $this->assertEquals($merge, implode("\n", $diff->mergedOutput('2.txt', '4.txt')));
    }
}
