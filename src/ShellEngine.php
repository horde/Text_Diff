<?php

declare(strict_types=1);

namespace Horde\Text\Diff;
use Horde\Util\Util;

/**
 * Class used internally by Diff to actually compute the diffs.
 *
 * This class uses the Unix `diff` program via shell_exec to compute the
 * differences between the two input arrays.
 *
 * Copyright 2007-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author  Milian Wolff <mail@milianw.de>
 * @package Text_Diff
 */
class ShellEngine implements DiffEngineInterface
{
    /**
     * Constructor.
     *
     * @param array $fromLines lines of text from old file
     * @param array $toLines   lines of text from new file
     * @param string $diffCommand The external diff tool to call
     *
     */
    public function __construct(
        private array $fromLines,
        private array $toLines,
        /**
         * Path to the diff executable
         *
         * @var string
         */
        protected string $diffCommand = 'diff'
    )
    {        
    }

    /**
     * Returns the array of differences.
     *
     * @return OperationList all changes made
     */
    public function diff(): OperationList
    {
        $from_lines = $this->fromLines;
        $to_lines = $this->toLines;        
        array_walk($from_lines, [Diff::class, 'trimNewlines']);
        array_walk($to_lines, [Diff::class, 'trimNewlines']);

        // Execute gnu diff or similar to get a standard diff file.
        $from_file = Util::getTempFile('Horde_Text_Diff');
        $to_file = Util::getTempFile('Horde_Text_Diff');
        $fp = fopen($from_file, 'w');
        fwrite($fp, implode("\n", $from_lines));
        fclose($fp);
        $fp = fopen($to_file, 'w');
        fwrite($fp, implode("\n", $to_lines));
        fclose($fp);
        $diff = shell_exec($this->diffCommand . ' ' . $from_file . ' ' . $to_file);
        unlink($from_file);
        unlink($to_file);

        if (is_null($diff)) {
            // No changes were made
            return new OperationList(new CopyOperation($from_lines));
        }

        $from_line_no = 1;
        $to_line_no = 1;
        $edits = [];

        // Get changed lines by parsing something like:
        // 0a1,2
        // 1,2c4,6
        // 1,5d6
        preg_match_all(
            '#^(\d+)(?:,(\d+))?([adc])(\d+)(?:,(\d+))?$#m',
            $diff,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            if (!isset($match[5])) {
                // This paren is not set every time (see regex).
                $match[5] = false;
            }

            if ($match[3] == 'a') {
                $from_line_no--;
            }

            if ($match[3] == 'd') {
                $to_line_no--;
            }

            if ($from_line_no < $match[1] || $to_line_no < $match[4]) {
                // copied lines
                assert($match[1] - $from_line_no == $match[4] - $to_line_no);
                $edits[] =
                    new CopyOperation(
                        $this->_getLines($from_lines, $from_line_no, $match[1] - 1),
                        $this->_getLines($to_lines, $to_line_no, $match[4] - 1)
                    );
            }

            switch ($match[3]) {
                case 'd':
                    // deleted lines
                    $edits[] =
                        new DeleteOperation(
                            $this->_getLines($from_lines, $from_line_no, (int) $match[2])
                        );
                    $to_line_no++;
                    break;

                case 'c':
                    // changed lines
                    $edits[] =
                        new ChangeOperation(
                            $this->_getLines($from_lines, $from_line_no, (int) $match[2]),
                            $this->_getLines($to_lines, $to_line_no, (int) $match[5])
                        );
                    break;

                case 'a':
                    // added lines
                    $edits[] =
                        new AddOperation(
                            $this->_getLines($to_lines, $to_line_no, (int) $match[5])
                        );
                    $from_line_no++;
                    break;
            }
        }

        if (!empty($from_lines)) {
            // Some lines might still be pending. Add them as copied
            $edits[] =
                new CopyOperation(
                    $this->_getLines(
                        $from_lines,
                        $from_line_no,
                        $from_line_no + count($from_lines) - 1
                    ),
                    $this->_getLines(
                        $to_lines,
                        $to_line_no,
                        $to_line_no + count($to_lines) - 1
                    )
                );
        }

        return new OperationList(...$edits);
    }

    /**
     * Get lines from either the old or new text
     *
     * @access private
     *
     * @param array &$text_lines Either $from_lines or $to_lines
     * @param int   &$line_no    Current line number
     * @param int   $end         Optional end line, when we want to chop more
     *                           than one line.
     *
     * @return array The chopped lines
     */
    protected function _getLines(&$text_lines, &$line_no, int $end = 0)
    {
        if (!empty($end)) {
            $lines = [];
            // We can shift even more
            while ($line_no <= $end) {
                $lines[] = array_shift($text_lines);
                $line_no++;
            }
        } else {
            $lines = [array_shift($text_lines)];
            $line_no++;
        }

        return $lines;
    }
}
