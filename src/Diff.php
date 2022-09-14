<?php

declare(strict_types=1);

namespace Horde\Text\Diff;
/**
 * General API for generating and formatting diffs - the differences between
 * two sequences of strings.
 *
 * The original PHP version of this code was written by Geoffrey T. Dairiki
 * <dairiki@dairiki.org>, and is used/adapted with his permission.
 *
 * Copyright 2004 Geoffrey T. Dairiki <dairiki@dairiki.org>
 * Copyright 2004-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 */
class Diff
{
    /**
     * Computes diffs between sequences of strings.
     *
     * @param OperationList $edits
     */
    public function __construct(protected OperationList $edits)
    {
    }

    /**
     * Shortcut constructor, internally creating the Engine instance.
     *
     * Default is Auto, meaning it will use XDiffEngine if available, otherwise resort to NativeEngine 
     * If you really care about what engine provides the OperationList, implement your own 
     * 
     * Use this to create
     * - NativeEngine
     * - XDiffEngine
     * - ShellEngine
     * - "Auto": The most appropriate engine to deal with two arrays of file lines
     * - Explicitly any other engine that initializes from two arrays of lines
     * 
     * @return Diff
     */
    public static function fromFileLineArrays(
        array $fromLines = [],
        array $toLines = [],
        string $engineClass = 'auto',
        array $engineParams = []
    ): Diff
    {
        $engine = DiffEngineFactory::fromFileLineArrays($fromLines, $toLines, $engineClass, $engineParams);
        return new self($engine->diff());
    }
   /**
    * Shortcut constructor, internally creating the Engine instance.
    *
    * Default is Auto, meaning it will use XDiffEngine if available, otherwise resort to NativeEngine 
    * If you really care about what engine provides the OperationList, implement your own 
    * 
    * Use this to create
    * - StringEngine
    * - "Auto": The most appropriate engine to deal with a single string diff source
    * - Explicitly any other engine that initializes from a single string diff source
    * 
    * @return Diff 
    */
    public static function fromString(
        string $diff,
        string $engineClass = 'auto',
        $engineParams = ['mode' => 'autodetect']
    ): Diff
    {
        $engine = DiffEngineFactory::fromString($diff, $engineClass, $engineParams);
        return new self($engine->diff());
    }
    /**
     * Returns the array of differences.
     */
    public function getDiff()
    {
        return $this->edits;
    }

    /**
     * returns the number of new (added) lines in a given diff.
     *
     * @return integer The number of new lines
     */
    public function countAddedLines()
    {
        $count = 0;
        foreach ($this->edits as $edit) {
            if ($edit instanceof AddOperation ||
                $edit instanceof ChangeOperation) {
                $count += $edit->nfinal();
            }
        }
        return $count;
    }

    /**
     * Returns the number of deleted (removed) lines in a given diff.
     *
     * @return integer The number of deleted lines
     */
    public function countDeletedLines()
    {
        $count = 0;
        foreach ($this->edits as $edit) {
            if ($edit instanceof DeleteOperation ||
                $edit instanceof ChangeOperation) {
                $count += $edit->norig();
            }
        }
        return $count;
    }

    /**
     * Computes a reversed diff.
     *
     * Example:
     * <code>
     * $diff = new Horde_Text_Diff($lines1, $lines2);
     * $rev = $diff->reverse();
     * </code>
     *
     * @return Diff  A Diff object representing the inverse of the
     *                    original diff.  Note that we purposely don't return a
     *                    reference here, since this essentially is a clone()
     *                    method.
     */
    public function reverse(): Diff
    {
        $revEdits = [];
        foreach ($this->edits as $edit) {
            $revEdits[] = $edit->reverse();
        }
        return new Diff(new OperationList(...$revEdits));
    }

    /**
     * Checks for an empty diff.
     *
     * @return bool  True if two sequences were identical.
     */
    public function isEmpty(): bool
    {
        foreach ($this->edits as $edit) {
            if (!($edit instanceof CopyOperation)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Computes the length of the Longest Common Subsequence (LCS).
     *
     * This is mostly for diagnostic purposes.
     *
     * @return int  The length of the LCS.
     */
    public function lcs(): int
    {
        $lcs = 0;
        foreach ($this->edits as $edit) {
            if ($edit instanceof CopyOperation) {
                $lcs += count($edit->orig);
            }
        }
        return $lcs;
    }

    /**
     * Gets the original set of lines.
     *
     * This reconstructs the $from_lines parameter passed to the constructor.
     *
     * @return array  The original sequence of strings.
     */
    public function getOriginal(): array
    {
        $lines = [];
        foreach ($this->edits as $edit) {
            if ($edit->orig) {
                array_splice($lines, count($lines), 0, $edit->orig);
            }
        }
        return $lines;
    }

    /**
     * Gets the final set of lines.
     *
     * This reconstructs the $to_lines parameter passed to the constructor.
     *
     * @return array  The sequence of strings.
     */
    public function getFinal(): array
    {
        $lines = [];
        foreach ($this->edits as $edit) {
            if ($edit->final) {
                array_splice($lines, count($lines), 0, $edit->final);
            }
        }
        return $lines;
    }

    /**
     * Removes trailing newlines from a line of text. This is meant to be used
     * with array_walk().
     *
     * @param string $line  The line to trim.
     * @param integer $key  The index of the line in the array. Not used.
     */
    public static function trimNewlines(&$line, int $key)
    {
        $line = str_replace(["\n", "\r"], '', $line);
    }

    /**
     * Checks a diff for validity.
     *
     * This is here only for debugging purposes.
     */
    protected function _check($from_lines, $to_lines)
    {
        if (serialize($from_lines) != serialize($this->getOriginal())) {
            trigger_error("Reconstructed original doesn't match", E_USER_ERROR);
        }
        if (serialize($to_lines) != serialize($this->getFinal())) {
            trigger_error("Reconstructed final doesn't match", E_USER_ERROR);
        }

        $rev = $this->reverse();
        if (serialize($to_lines) != serialize($rev->getOriginal())) {
            trigger_error("Reversed original doesn't match", E_USER_ERROR);
        }
        if (serialize($from_lines) != serialize($rev->getFinal())) {
            trigger_error("Reversed final doesn't match", E_USER_ERROR);
        }

        $prevtype = null;
        foreach ($this->edits as $edit) {
            if ($prevtype == get_class($edit)) {
                trigger_error("Edit sequence is non-optimal", E_USER_ERROR);
            }
            $prevtype = get_class($edit);
        }

        return true;
    }
}
