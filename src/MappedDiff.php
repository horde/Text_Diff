<?php

declare(strict_types=1);
/**
 * Copyright 2007-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Geoffrey T. Dairiki <dairiki@dairiki.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package  Text_Diff
 */

namespace Horde\Text\Diff;

/**
 * This can be used to compute things like case-insensitve diffs, or diffs
 * which ignore changes in white-space.
 *
 * @author    Geoffrey T. Dairiki <dairiki@dairiki.org>
 * @category  Horde
 * @copyright 2007-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package   Text_Diff
 */
class MappedDiff extends Diff
{
    /**
     * Computes a diff between sequences of strings.
     *
     * @param string $engine  Name of the diffing engine to use.  'auto' will
     *                        automatically select the best.
     * @param array $params   Parameters to pass to the diffing engine:
     *                        - Two arrays, each containing the lines from a
     *                          file.
     *                        - Two arrays with the same size as the first
     *                          parameters. The elements are what is actually
     *                          compared when computing the diff.
     */
    public function __construct($engine, $params)
    {
        [$from_lines, $to_lines, $mapped_from_lines, $mapped_to_lines] = $params;
        assert(count($from_lines) == count($mapped_from_lines));
        assert(count($to_lines) == count($mapped_to_lines));

        parent::__construct($engine, [$mapped_from_lines, $mapped_to_lines]);

        $xi = $yi = 0;
        for ($i = 0; $i < count($this->edits); $i++) {
            $orig = &$this->edits[$i]->orig;
            if (is_array($orig)) {
                $orig = array_slice($from_lines, $xi, count($orig));
                $xi += count($orig);
            }

            $final = &$this->edits[$i]->final;
            if (is_array($final)) {
                $final = array_slice($to_lines, $yi, count($final));
                $yi += count($final);
            }
        }
    }
}
