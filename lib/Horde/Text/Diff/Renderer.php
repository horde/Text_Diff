<?php
/**
 * A class to render Diffs in different formats.
 *
 * This class renders the diff in classic diff format. It is intended that
 * this class be customized via inheritance, to obtain fancier outputs.
 *
 * Copyright 2004-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @package Text_Diff
 */
class Horde_Text_Diff_Renderer
{
    /**
     * Number of leading context "lines" to preserve.
     *
     * This should be left at zero for this class, but subclasses may want to
     * set this to other values.
     */
    protected int $_leading_context_lines = 0;

    /**
     * Number of trailing context "lines" to preserve.
     *
     * This should be left at zero for this class, but subclasses may want to
     * set this to other values.
     */
    protected int $_trailing_context_lines = 0;

    /**
     * Constructor.
     */
    public function __construct($params = [])
    {
        foreach ($params as $param => $value) {
            $v = '_' . $param;
            if (isset($this->{$v})) {
                $this->{$v} = $value;
            }
        }
    }

    /**
     * Get any renderer parameters.
     *
     * @return array  All parameters of this renderer object.
     */
    public function getParams(): array
    {
        $params = [];
        foreach (get_object_vars($this) as $k => $v) {
            if ($k[0] === '_') {
                $params[substr($k, 1)] = $v;
            }
        }

        return $params;
    }

    public function render(Horde_Text_Diff $diff): string
    {
        $xi = $yi = 1;
        $block = false;
        $context = [];

        $nlead = $this->_leading_context_lines;
        $ntrail = $this->_trailing_context_lines;

        $output = $this->_startDiff();

        $diffs = $diff->getDiff();
        foreach ($diffs as $i => $edit) {
            /* If these are unchanged (copied) lines, and we want to keep
             * leading or trailing context lines, extract them from the copy
             * block. */
            if ($edit instanceof Horde_Text_Diff_Op_Copy) {
                /* Do we have any diff blocks yet? */
                if (is_array($block)) {
                    /* How many lines to keep as context from the copy
                     * block. */
                    $keep = $i == count($diffs) - 1 ? $ntrail : $nlead + $ntrail;
                    if (count($edit->orig) <= $keep) {
                        /* We have less lines in the block than we want for
                         * context => keep the whole block. */
                        $block[] = $edit;
                    } else {
                        if ($ntrail) {
                            /* Create a new block with as many lines as we need
                             * for the trailing context. */
                            $context = array_slice($edit->orig, 0, $ntrail);
                            $block[] = new Horde_Text_Diff_Op_Copy($context);
                        }
                        /* @todo */
                        $output .= $this->_block($x0, $ntrail + $xi - $x0,
                                                 $y0, $ntrail + $yi - $y0,
                                                 $block);
                        $block = false;
                    }
                }
                /* Keep the copy block as the context for the next block. */
                $context = $edit->orig;
            } else {
                /* Don't we have any diff blocks yet? */
                if (!is_array($block)) {
                    /* Extract context lines from the preceding copy block. */
                    $context = array_slice($context, count($context) - $nlead);
                    $x0 = $xi - count($context);
                    $y0 = $yi - count($context);
                    $block = [];
                    if ($context) {
                        $block[] = new Horde_Text_Diff_Op_Copy($context);
                    }
                }
                $block[] = $edit;
            }

            if ($edit->orig) {
                $xi += count($edit->orig);
            }
            if ($edit->final) {
                $yi += count($edit->final);
            }
        }

        if (is_array($block)) {
            $output .= $this->_block($x0, $xi - $x0,
                                     $y0, $yi - $y0,
                                     $block);
        }

        return $output . $this->_endDiff();
    }

    protected function _block($xbeg, $xlen, $ybeg, $ylen, $edits): string
    {
        $output = $this->_startBlock($this->_blockHeader($xbeg, $xlen, $ybeg, $ylen));

        foreach ($edits as $edit) {
            $output .= match (get_class($edit)) {
                'Horde_Text_Diff_Op_Copy' => $this->_context($edit->orig),
                'Horde_Text_Diff_Op_Add' => $this->_added($edit->final),
                'Horde_Text_Diff_Op_Delete' => $this->_deleted($edit->orig),
                'Horde_Text_Diff_Op_Change' => $this->_changed($edit->orig, $edit->final),
            };
        }

        return $output . $this->_endBlock();
    }

    protected function _startDiff(): string
    {
        return '';
    }

    protected function _endDiff(): string
    {
        return '';
    }

    protected function _blockHeader($xbeg, $xlen, $ybeg, $ylen): string
    {
        if ($xlen > 1) {
            $xbeg .= ',' . ($xbeg + $xlen - 1);
        }
        if ($ylen > 1) {
            $ybeg .= ',' . ($ybeg + $ylen - 1);
        }

        // this matches the GNU Diff behaviour
        if ($xlen && !$ylen) {
            $ybeg--;
        } elseif (!$xlen) {
            $xbeg--;
        }

        return $xbeg . ($xlen ? ($ylen ? 'c' : 'd') : 'a') . $ybeg;
    }

    protected function _startBlock($header): string
    {
        return $header . "\n";
    }

    protected function _endBlock(): string
    {
        return '';
    }

    protected function _lines($lines, $prefix = ' '): string
    {
        return $prefix . implode("\n$prefix", $lines) . "\n";
    }

    protected function _context($lines): string
    {
        return $this->_lines($lines, '  ');
    }

    protected function _added($lines): string
    {
        return $this->_lines($lines, '> ');
    }

    protected function _deleted($lines): string
    {
        return $this->_lines($lines, '< ');
    }

    protected function _changed($orig, $final): string
    {
        return $this->_deleted($orig) . "---\n" . $this->_added($final);
    }
}
