<?php
/**
 * Copyright 2007-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 */
class Horde_Text_Diff_ThreeWay_BlockBuilder
{
    public function __construct()
    {
        $this->_init();
    }

    public function input($lines): void
    {
        if ($lines) {
            $this->_append($this->orig, $lines);
        }
    }

    public function out1($lines): void
    {
        if ($lines) {
            $this->_append($this->final1, $lines);
        }
    }

    public function out2($lines): void
    {
        if ($lines) {
            $this->_append($this->final2, $lines);
        }
    }

    public function isEmpty(): bool
    {
        return !$this->orig && !$this->final1 && !$this->final2;
    }

    public function finish(): bool|Horde_Text_Diff_ThreeWay_Op_Base
    {
        if ($this->isEmpty()) {
            return false;
        } else {
            $edit = new Horde_Text_Diff_ThreeWay_Op_Base($this->orig, $this->final1, $this->final2);
            $this->_init();
            return $edit;
        }
    }

    protected function _init(): void
    {
        $this->orig = $this->final1 = $this->final2 = [];
    }

    protected function _append(&$array, $lines): void
    {
        array_splice($array, count($array), 0, $lines);
    }
}
