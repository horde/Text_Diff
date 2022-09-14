<?php

declare(strict_types=1);

namespace Horde\Text\Diff;

use Horde\Text\Diff;

/**
 * "Unified" diff renderer.
 *
 * This class renders the diff in classic "unified diff" format.
 *
 * Copyright 2004-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author  Ciprian Popovici
 * @package Text_Diff
 */
class UnifiedRenderer extends Renderer
{
    /**
     * Number of leading context "lines" to preserve.
     */
    protected $_leading_context_lines = 4;

    /**
     * Number of trailing context "lines" to preserve.
     */
    protected $_trailing_context_lines = 4;

    protected function _blockHeader(int $xbeg, int $xlen, int $ybeg, int $ylen): string
    {
        if ($xlen != 1) {
            $xbeg .= ',' . $xlen;
        }
        if ($ylen != 1) {
            $ybeg .= ',' . $ylen;
        }
        return "@@ -$xbeg +$ybeg @@";
    }

    protected function _context(array $lines = []): string
    {
        return $this->_lines($lines, ' ');
    }

    protected function _added(array $lines = []): string
    {
        return $this->_lines($lines, '+');
    }

    protected function _deleted(array $lines = []): string
    {
        return $this->_lines($lines, '-');
    }

    protected function _changed(array $orig = [], array $final = []): string
    {
        return $this->_deleted($orig) . $this->_added($final);
    }
}
