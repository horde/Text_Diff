<?php
/**
 * A class for computing three way diffs.
 *
 * Copyright 2007-2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://opensource.org/licenses/lgpl-license.php.
 *
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 */
class Horde_Text_Diff_ThreeWay extends Horde_Text_Diff
{
    /**
     * Conflict counter.
     *
     * @var integer
     */
    protected $_conflictingBlocks = 0;

    /**
     * Computes diff between 3 sequences of strings.
     *
     * @param array $orig    The original lines to use.
     * @param array $final1  The first version to compare to.
     * @param array $final2  The second version to compare to.
     */
    public function __construct($orig, $final1, $final2)
    {
        if (extension_loaded('xdiff')) {
            $engine = new Horde_Text_Diff_Engine_xdiff();
        } else {
            $engine = new Horde_Text_Diff_Engine_native();
        }

        $this->_edits = $this->_diff3($engine->diff($orig, $final1),
                                      $engine->diff($orig, $final2));
    }

    /**
     */
    public function mergedOutput($label1 = false, $label2 = false)
    {
        $lines = array();
        foreach ($this->_edits as $edit) {
            if ($edit->isConflict()) {
                /* FIXME: this should probably be moved somewhere else. */
                $lines = array_merge($lines,
                                     array('<<<<<<<' . ($label1 ? ' ' . $label1 : '')),
                                     $edit->final1,
                                     array("======="),
                                     $edit->final2,
                                     array('>>>>>>>' . ($label2 ? ' ' . $label2 : '')));
                $this->_conflictingBlocks++;
            } else {
                $lines = array_merge($lines, $edit->merged());
            }
        }

        return $lines;
    }

    /**
     * @access private
     */
    protected function _diff3($edits1, $edits2)
    {
        $edits = array();
        $bb = new Horde_Text_Diff_ThreeWay_BlockBuilder();

        $e1 = current($edits1);
        $e2 = current($edits2);
        while ($e1 || $e2) {
            if ($e1 && $e2 &&
                $e1 instanceof Horde_Text_Diff_Op_Copy &&
                $e2 instanceof Horde_Text_Diff_Op_Copy) {
                /* We have copy blocks from both diffs. This is the (only)
                 * time we want to emit a diff3 copy block.  Flush current
                 * diff3 diff block, if any. */
                if ($edit = $bb->finish()) {
                    $edits[] = $edit;
                }

                $ncopy = min($e1->norig(), $e2->norig());
                assert($ncopy > 0);
                $edits[] = new Horde_Text_Diff_ThreeWay_Op_copy(array_slice($e1->orig, 0, $ncopy));

                if ($e1->norig() > $ncopy) {
                    array_splice($e1->orig, 0, $ncopy);
                    array_splice($e1->final, 0, $ncopy);
                } else {
                    $e1 = next($edits1);
                }

                if ($e2->norig() > $ncopy) {
                    array_splice($e2->orig, 0, $ncopy);
                    array_splice($e2->final, 0, $ncopy);
                } else {
                    $e2 = next($edits2);
                }
            } else {
                if ($e1 && $e2) {
                    if ($e1->orig && $e2->orig) {
                        $norig = min($e1->norig(), $e2->norig());
                        $orig = array_splice($e1->orig, 0, $norig);
                        array_splice($e2->orig, 0, $norig);
                        $bb->input($orig);
                    }

                    if ($e1 instanceof Horde_Text_Diff_Op_Copy) {
                        $bb->out1(array_splice($e1->final, 0, $norig));
                    }

                    if ($e2 instanceof Horde_Text_Diff_Op_Copy) {
                        $bb->out2(array_splice($e2->final, 0, $norig));
                    }
                }

                if ($e1 && ! $e1->orig) {
                    $bb->out1($e1->final);
                    $e1 = next($edits1);
                }
                if ($e2 && ! $e2->orig) {
                    $bb->out2($e2->final);
                    $e2 = next($edits2);
                }
            }
        }

        if ($edit = $bb->finish()) {
            $edits[] = $edit;
        }

        return $edits;
    }

}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @access private
 */
class Horde_Text_Diff_ThreeWay_Op {

    public function Horde_Text_Diff_ThreeWay_Op($orig = false, $final1 = false, $final2 = false)
    {
        $this->orig = $orig ? $orig : array();
        $this->final1 = $final1 ? $final1 : array();
        $this->final2 = $final2 ? $final2 : array();
    }

    public function merged()
    {
        if (!isset($this->_merged)) {
            if ($this->final1 === $this->final2) {
                $this->_merged = &$this->final1;
            } elseif ($this->final1 === $this->orig) {
                $this->_merged = &$this->final2;
            } elseif ($this->final2 === $this->orig) {
                $this->_merged = &$this->final1;
            } else {
                $this->_merged = false;
            }
        }

        return $this->_merged;
    }

    public function isConflict()
    {
        return $this->merged() === false;
    }

}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @access private
 */
class Horde_Text_Diff_ThreeWay_Op_copy extends Horde_Text_Diff_ThreeWay_Op {

    public function Horde_Text_Diff_ThreeWay_Op_Copy($lines = false)
    {
        $this->orig = $lines ? $lines : array();
        $this->final1 = &$this->orig;
        $this->final2 = &$this->orig;
    }

    public function merged()
    {
        return $this->orig;
    }

    public function isConflict()
    {
        return false;
    }

}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @access private
 */
class Horde_Text_Diff_ThreeWay_BlockBuilder {

    public function Horde_Text_Diff_ThreeWay_BlockBuilder()
    {
        $this->_init();
    }

    public function input($lines)
    {
        if ($lines) {
            $this->_append($this->orig, $lines);
        }
    }

    public function out1($lines)
    {
        if ($lines) {
            $this->_append($this->final1, $lines);
        }
    }

    public function out2($lines)
    {
        if ($lines) {
            $this->_append($this->final2, $lines);
        }
    }

    public function isEmpty()
    {
        return !$this->orig && !$this->final1 && !$this->final2;
    }

    public function finish()
    {
        if ($this->isEmpty()) {
            return false;
        } else {
            $edit = new Horde_Text_Diff_ThreeWay_Op($this->orig, $this->final1, $this->final2);
            $this->_init();
            return $edit;
        }
    }

    protected function _init()
    {
        $this->orig = $this->final1 = $this->final2 = array();
    }

    protected function _append(&$array, $lines)
    {
        array_splice($array, sizeof($array), 0, $lines);
    }
}