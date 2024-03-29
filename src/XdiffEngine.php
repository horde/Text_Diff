<?php

declare(strict_types=1);

namespace Horde\Text\Diff;
use function xdiff_string_diff;
/**
 * Class used internally by Diff to actually compute the diffs.
 *
 * This class uses the xdiff PECL package (http://pecl.php.net/package/xdiff)
 * to compute the differences between the two input arrays.
 *
 * Copyright 2004-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author  Jon Parise <jon@horde.org>
 * @package Text_Diff
 */
class XdiffEngine implements DiffEngineInterface
{
    public function __construct(private array $fromLines, private array $toLines)
    {
    }
    /**
     * @return OperationList all changes made
     */
    public function diff(): OperationList
    {
        $from_lines = $this->fromLines;
        $to_lines = $this->toLines;
        if (!extension_loaded('xdiff')) {
            throw new Exception('The xdiff extension is required for this diff engine');
        }

        array_walk($from_lines, [Diff::class, 'trimNewlines']);
        array_walk($to_lines, [Diff::class, 'trimNewlines']);

        /* Convert the two input arrays into strings for xdiff processing. */
        $from_string = implode("\n", $from_lines);
        $to_string = implode("\n", $to_lines);

        /* Diff the two strings and convert the result to an array. */
        $diff = xdiff_string_diff($from_string, $to_string, count($to_lines));
        $diff = explode("\n", $diff);

        /* Walk through the diff one line at a time.  We build the $edits
         * array of diff operations by reading the first character of the
         * xdiff output (which is in the "unified diff" format).
         *
         * Note that we don't have enough information to detect "changed"
         * lines using this approach, so we can't add Horde_Text_Diff_Op_Changed
         * instances to the $edits array.  The result is still perfectly
         * valid, albeit a little less descriptive and efficient. */
        $edits = [];
        foreach ($diff as $line) {
            if (!strlen($line)) {
                continue;
            }
            switch ($line[0]) {
                case ' ':
                    $edits[] = new CopyOperation([substr($line, 1)]);
                    break;

                case '+':
                    $edits[] = new AddOperation([substr($line, 1)]);
                    break;

                case '-':
                    $edits[] = new DeleteOperation([substr($line, 1)]);
                    break;
            }
        }

        return new OperationList(...$edits);
    }
}
