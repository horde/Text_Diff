<?php

declare(strict_types=1);

namespace Horde\Text\Diff;

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
class Renderer
{
    /**
     * Number of leading context "lines" to preserve.
     *
     * This should be left at zero for this class, but subclasses may want to
     * set this to other values.
     */
    protected $_leading_context_lines = 0;

    /**
     * Number of trailing context "lines" to preserve.
     *
     * This should be left at zero for this class, but subclasses may want to
     * set this to other values.
     */
    protected $_trailing_context_lines = 0;

    /**
     * Constructor.
     */
    public function __construct($params = [])
    {
        foreach ($params as $param => $value) {
            $v = '_' . $param;
            if (isset($this->$v)) {
                $this->$v = $value;
            }
        }
    }

    /**
     * Get any renderer parameters.
     *
     * @return array  All parameters of this renderer object.
     */
    public function getParams()
    {
        $params = [];
        foreach (get_object_vars($this) as $k => $v) {
            if ($k[0] == '_') {
                $params[substr($k, 1)] = $v;
            }
        }

        return $params;
    }

    /**
     * Renders a diff.
     *
     * @param Diff $diff  A Horde_Text_Diff object.
     *
     * @return string  The formatted output.
     */
    public function render(Diff $diff)
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
            if ($edit instanceof CopyOperation) {
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
                            $block[] = new CopyOperation($context);
                        }
                        /* @todo */
                        $x0 ??= 0;
                        $y0 ??= 0;
                        $output .= $this->_block(
                            (int) $x0,
                            (int) $ntrail + $xi - $x0,
                            (int) $y0,
                            (int) $ntrail + $yi - $y0,
                            $block
                        );
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
                        $block[] = new CopyOperation($context);
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
            $x0 ??= 0;
            $y0 ??= 0;
            $output .= $this->_block(
                (int) $x0,
                (int) $xi - $x0,
                (int) $y0,
                (int) $yi - $y0,
                $block
            );
        }

        return $output . $this->_endDiff();
    }

    /**
     * Render a diff block
     * 
     * @param int $xbeg 
     * @param int $xlen 
     * @param int $ybeg 
     * @param int $ylen 
     * @param mixed $edits Should be a list object rather than a writeable array reference
     * @return string 
     */
    protected function _block(int $xbeg, int $xlen, int $ybeg, int $ylen, &$edits): string
    {
        $output = $this->_startBlock($this->_blockHeader($xbeg, $xlen, $ybeg, $ylen));

        foreach ($edits as $edit) {
            switch (get_class($edit)) {
                case CopyOperation::class:
                    $output .= $this->_context($edit->orig);
                    break;

                case AddOperation::class:
                    $output .= $this->_added($edit->final);
                    break;

                case DeleteOperation::class:
                    $output .= $this->_deleted($edit->orig);
                    break;

                case ChangeOperation::class:
                    $output .= $this->_changed($edit->orig, $edit->final);
                    break;
            }
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

    /**
     * Render a Diff Block Header
     * 
     * Headers look like: 186,187c180,181 or 204c198
     * 
     * @param int $xbeg
     * @param int $xlen 
     * @param int $ybeg 
     * @param int $ylen 
     * @return string 
     */
    protected function _blockHeader(int $xbeg, int $xlen, int $ybeg, int $ylen): string
    {
        if ($xlen > 1) {
            $xbeg .= ',' . (string)($xbeg + $xlen - 1);
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

    protected function _startBlock(string $header): string
    {
        return $header . "\n";
    }

    protected function _endBlock(): string
    {
        return '';
    }

    /**
     * Glue an array of lines to a string with prefixed lines
     * 
     * @param array $lines 
     * @param string $prefix defaults to a single space
     * @return string 
     */
    protected function _lines(array $lines, string $prefix = ' '): string
    {
        return $prefix . implode("\n$prefix", $lines) . "\n";
    }

    /**
     * Glues array of context lines to a space-prefixed string
     * 
     * @param array $lines
     * @return string 
     */
    protected function _context(array $lines = []): string
    {
        return $this->_lines($lines, '  ');
    }

    /**
     * Glues array of added lines to a >-prefixed string
     * 
     * @param array $lines
     * @return string 
     */
    protected function _added(array $lines = []): string
    {
        return $this->_lines($lines, '> ');
    }

    /**
     * Glues array of added lines to a >-prefixed string
     * 
     * @param array $lines
     * @return string 
     */
    protected function _deleted(array $lines = []): string
    {
        return $this->_lines($lines, '< ');
    }

    /**
     * Produces a comparison string out of arrays of deleted and added lines
     * 
     * @param array $orig 
     * @param array $final 
     * @return string 
     */
    protected function _changed(array $orig = [], array $final = []): string
    {
        return $this->_deleted($orig) . "---\n" . $this->_added($final);
    }
}
