<?php
/**
 * A class for computing three way merges.
 *
 * Copyright 2007-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 */
class Horde_Text_Diff_ThreeWay
{
    /**
     * Array of changes.
     *
     * @var array
     */
    protected $_edits;

    /**
     * Conflict counter.
     *
     * @var integer
     */
    protected $_conflictingBlocks = 0;

    /**
     * Computes diff between 3 sequences of strings.
     *
     * @param array|string|object|string $orig array: The original lines to use.
     *                                         OR: string denoting the engine to use
     *                                         OR: an Engine object
     * @param array $final1  The first version to compare to OR if $orig was an engine string or object, the original array of lines.
     * @param array $final2  The second version to compare to OR if $orig was an engine string or object, the first version to compare to as array of lines.
     * @param string|object|string $engine Either 'auto' or a Fully Qualified Class Name, a shorthand or an object OR if the first argument was the engine, the second version to compare to as array of lines.
     */
    public function __construct($orig = null, $final1 = null, $final2 = null, $engine = 'auto')
    {
        if (is_string($orig) || is_object($orig)) {
            $tmp = $orig;
            $orig = $final1;
            $final1 = $final2;
            $final2 = $engine;
            $engine = $tmp;
        }
        if (!(is_array($orig) && is_array($final2) && (is_string($engine) || is_object($engine)))) {
            throw new InvalidArgumentException(
                sprintf('ThreeWay must be initialized with three arrays of strings and optionally an engine object or class string. Found: __construct(%s, %s, %s, %s)',
                    gettype($orig),
                    gettype($final1),
                    gettype($final2),
                    gettype($engine)
                )
            );
        }
        if (!is_object($engine)) {
            $class = 'Horde_Text_Diff_Engine_';
            if ($engine == 'auto') {
                $class .= extension_loaded('xdiff') ? 'Xdiff' : 'Native';
            } elseif (strpos($engine, '_') === false && strpos($engine, '\\') === false) {
                $class .= Horde_String::ucfirst(basename($engine));
            } else {
                $class = $engine;
            }
            $engine = new $class();
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
                                     $edit->getFinal1(),
                                     array("======="),
                                     $edit->getFinal2(),
                                     array('>>>>>>>' . ($label2 ? ' ' . $label2 : '')));
                $this->_conflictingBlocks++;
            } else {
                $lines = array_merge($lines, $edit->merged());
            }
        }

        return $lines;
    }

    /**
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
                $edits[] = new Horde_Text_Diff_ThreeWay_Op_Copy(array_slice($e1->orig, 0, $ncopy));

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
