<?php

declare(strict_types=1);

namespace Horde\Text\Diff;


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
class ThreeWay
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
     * @param OperationList $final1  The first version to compare to.
     * @param OperationList $final2  The second version to compare to.
     */
    public function __construct(OperationList $origFinal1, OperationList $origFinal2)
    {
        $this->_edits = $this->_diff3(
            $origFinal1->toArray(),
            $origFinal2->toArray(),
        );
    }

    /**
     */
    public function mergedOutput($label1 = false, $label2 = false)
    {
        $lines = [];
        foreach ($this->_edits as $edit) {
            if ($edit->isConflict()) {
                /* FIXME: this should probably be moved somewhere else. */
                $lines = array_merge(
                    $lines,
                    ['<<<<<<<' . ($label1 ? ' ' . $label1 : '')],
                    $edit->getFinal1(),
                    ["======="],
                    $edit->getFinal2(),
                    ['>>>>>>>' . ($label2 ? ' ' . $label2 : '')]
                );
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
        $edits = [];
        $bb = new ThreeWayBlockBuilder();
        $norig = 0;

        /**
         * @var OperationInterface
         */
        $e1 = current($edits1);
        /**
         * @var OperationInterface
         */
        $e2 = current($edits2);
        // Compare two sets of edits pair-wise
        while ($e1 || $e2) {
            if ($e1 && $e2 &&
                $e1 instanceof CopyOperation &&
                $e2 instanceof CopyOperation) {
                /* We have copy blocks from both diffs. This is the (only)
                 * time we want to emit a diff3 copy block.  Flush current
                 * diff3 diff block, if any. */
                if ($edit = $bb->finish()) {
                    $edits[] = $edit;
                }

                $ncopy = min($e1->norig(), $e2->norig());
                assert($ncopy > 0);
                $edits[] = new ThreeWayCopyOperation(array_slice($e1->getOrig(), 0, $ncopy));

                if ($e1->norig() > $ncopy) {
                    array_splice($e1->getOrig(), 0, $ncopy);
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

                    if ($e1 instanceof CopyOperation) {
                        $bb->out1(array_splice($e1->final, 0, $norig));
                    }

                    if ($e2 instanceof CopyOperation) {
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
