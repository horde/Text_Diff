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
class Horde_Text_Diff_ThreeWay_Op_Base
{
    /**
     * @var array|false|mixed
     */
    private mixed $_merged;
    /**
     * @var array|false|mixed
     */
    public mixed $orig;
    /**
     * @var array|false|mixed
     */
    public mixed $final1;
    /**
     * @var array|false|mixed
     */
    public mixed $final2;

    public function __construct($orig = false, $final1 = false, $final2 = false)
    {
        $this->orig = $orig ?: [];
        $this->final1 = $final1 ?: [];
        $this->final2 = $final2 ?: [];
    }

    public function merged(): mixed
    {
        if (!isset($this->_merged)) {
            if ($this->final1 === $this->final2) {
                $this->_merged = &$this->final1;
            } elseif ($this->final1 === $this->orig) {
                $this->_merged = &$this->final2;
            } elseif ($this->final2 === $this->orig) {
                $this->_merged = &$this->final1;
            } else {
                $this->_merged = false;
            }
        }

        return $this->_merged;
    }

    public function isConflict(): bool
    {
        return $this->merged() === false;
    }
}
