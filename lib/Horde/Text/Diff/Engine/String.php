<?php
/**
 * Parses unified or context diffs output from eg. the diff utility.
 *
 * Example:
 * <code>
 * $patch = file_get_contents('example.patch');
 * $diff = new Horde_Text_Diff('string', array($patch));
 * $renderer = new Horde_Text_Diff_Renderer_inline();
 * echo $renderer->render($diff);
 * </code>
 *
 * Copyright 2005 Örjan Persson <o@42mm.org>
 * Copyright 2005-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author  Örjan Persson <o@42mm.org>
 * @package Text_Diff
 */
class Horde_Text_Diff_Engine_String
{
    /**
     * Parses a unified or context diff.
     *
     * First param contains the whole diff and the second can be used to force
     * a specific diff type. If the second parameter is 'autodetect', the
     * diff will be examined to find out which type of diff this is.
     *
     * @param string $diff  The diff content.
     * @param string $mode  The diff mode of the content in $diff. One of
     *                      'context', 'unified', or 'autodetect'.
     *
     * @return array  List of all diff operations.
     * @throws Horde_Text_Diff_Exception
     */
    public function diff(string $diff, string $mode = 'autodetect'): array
    {
        // Detect line breaks.
        $lnbr = "\n";
        if (str_contains($diff, "\r\n")) {
            $lnbr = "\r\n";
        } elseif (str_contains($diff, "\r")) {
            $lnbr = "\r";
        }

        // Make sure we have a line break at the EOF.
        if (substr($diff, -strlen($lnbr)) != $lnbr) {
            $diff .= $lnbr;
        }

        if ($mode !== 'autodetect' && $mode !== 'context' && $mode !== 'unified') {
            throw new Horde_Text_Diff_Exception('Type of diff is unsupported');
        }

        if ($mode === 'autodetect') {
            $context = strpos($diff, '***');
            $unified = strpos($diff, '---');
            if ($context === $unified) {
                throw new Horde_Text_Diff_Exception('Type of diff could not be detected');
            } elseif ($context === false || $unified === false) {
                $mode = $context !== false ? 'context' : 'unified';
            } else {
                $mode = $context < $unified ? 'context' : 'unified';
            }
        }

        // Split by new line and remove the diff header, if there is one.
        $diff = explode($lnbr, $diff);
        if (($mode === 'context' && str_starts_with($diff[0], '***')) ||
            ($mode === 'unified' && str_starts_with($diff[0], '---'))) {
            array_shift($diff);
            array_shift($diff);
        }

        if ($mode === 'context') {
            return $this->parseContextDiff($diff);
        } else {
            return $this->parseUnifiedDiff($diff);
        }
    }

    /**
     * Parses an array containing the unified diff.
     *
     * @param array $diff  Array of lines.
     *
     * @return array  List of all diff operations.
     */
    public function parseUnifiedDiff(array $diff): array
    {
        $edits = [];
        $end = count($diff) - 1;
        for ($i = 0; $i < $end;) {
            $diff1 = [];
            switch (substr($diff[$i], 0, 1)) {
            case ' ':
                do {
                    $diff1[] = substr($diff[$i], 1);
                } while (++$i < $end && str_starts_with($diff[$i], ' '));
                $edits[] = new Horde_Text_Diff_Op_Copy($diff1);
                break;

            case '+':
                // get all new lines
                do {
                    $diff1[] = substr($diff[$i], 1);
                } while (++$i < $end && str_starts_with($diff[$i], '+'));
                $edits[] = new Horde_Text_Diff_Op_Add($diff1);
                break;

            case '-':
                // get changed or removed lines
                $diff2 = [];
                do {
                    $diff1[] = substr($diff[$i], 1);
                } while (++$i < $end && str_starts_with($diff[$i], '-'));

                while ($i < $end && str_starts_with($diff[$i], '+')) {
                    $diff2[] = substr($diff[$i++], 1);
                }
                if (count($diff2) == 0) {
                    $edits[] = new Horde_Text_Diff_Op_Delete($diff1);
                } else {
                    $edits[] = new Horde_Text_Diff_Op_Change($diff1, $diff2);
                }
                break;

            default:
                $i++;
                break;
            }
        }

        return $edits;
    }

    /**
     * Parses an array containing the context diff.
     *
     * @param array $diff  Array of lines.
     *
     * @return array  List of all diff operations.
     */
    public function parseContextDiff(array $diff): array
    {
        $edits = [];
        $i = $max_i = $j = $max_j = 0;
        $end = count($diff) - 1;
        while ($i < $end && $j < $end) {
            while ($i >= $max_i && $j >= $max_j) {
                // Find the boundaries of the diff output of the two files
                for ($i = $j;
                     $i < $end && str_starts_with($diff[$i], '***');
                     $i++);
                for ($max_i = $i;
                     $max_i < $end && !str_starts_with($diff[$max_i], '---');
                     $max_i++);
                for ($j = $max_i;
                     $j < $end && str_starts_with($diff[$j], '---');
                     $j++);
                for ($max_j = $j;
                     $max_j < $end && !str_starts_with($diff[$max_j], '***');
                     $max_j++);
            }

            // find what hasn't been changed
            $array = [];
            while ($i < $max_i &&
                   $j < $max_j &&
                   strcmp($diff[$i], $diff[$j]) == 0) {
                $array[] = substr($diff[$i], 2);
                $i++;
                $j++;
            }

            while ($i < $max_i && ($max_j-$j) <= 1) {
                if ($diff[$i] != '' && !str_starts_with($diff[$i], ' ')) {
                    break;
                }
                $array[] = substr($diff[$i++], 2);
            }

            while ($j < $max_j && ($max_i-$i) <= 1) {
                if ($diff[$j] != '' && !str_starts_with($diff[$j], ' ')) {
                    break;
                }
                $array[] = substr($diff[$j++], 2);
            }
            if (count($array) > 0) {
                $edits[] = new Horde_Text_Diff_Op_Copy($array);
            }

            if ($i < $max_i) {
                $diff1 = [];
                switch (substr($diff[$i], 0, 1)) {
                case '!':
                    $diff2 = [];
                    do {
                        $diff1[] = substr($diff[$i], 2);
                        if ($j < $max_j && str_starts_with($diff[$j], '!')) {
                            $diff2[] = substr($diff[$j++], 2);
                        }
                    } while (++$i < $max_i && str_starts_with($diff[$i], '!'));
                    $edits[] = new Horde_Text_Diff_Op_Change($diff1, $diff2);
                    break;

                case '+':
                    do {
                        $diff1[] = substr($diff[$i], 2);
                    } while (++$i < $max_i && str_starts_with($diff[$i], '+'));
                    $edits[] = new Horde_Text_Diff_Op_Add($diff1);
                    break;

                case '-':
                    do {
                        $diff1[] = substr($diff[$i], 2);
                    } while (++$i < $max_i && str_starts_with($diff[$i], '-'));
                    $edits[] = new Horde_Text_Diff_Op_Delete($diff1);
                    break;
                }
            }

            if ($j < $max_j) {
                $diff2 = [];
                switch (substr($diff[$j], 0, 1)) {
                case '+':
                    do {
                        $diff2[] = substr($diff[$j++], 2);
                    } while ($j < $max_j && str_starts_with($diff[$j], '+'));
                    $edits[] = new Horde_Text_Diff_Op_Add($diff2);
                    break;

                case '-':
                    do {
                        $diff2[] = substr($diff[$j++], 2);
                    } while ($j < $max_j && str_starts_with($diff[$j], '-'));
                    $edits[] = new Horde_Text_Diff_Op_Delete($diff2);
                    break;
                }
            }
        }

        return $edits;
    }
}
