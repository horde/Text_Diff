<?php

declare(strict_types=1);

namespace Horde\Text\Diff;

/**
 * Copyright 2007-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 */
class ThreeWayBaseOperation
{
    /**
     * @var array
     */
    protected $orig;

    /**
     * @var array
     */
    protected $final1;

    /**
     * @var array
     */
    protected $final2;

    /**
     * @var array|false
     */
    protected $_merged;


    public function __construct($orig = false, $final1 = false, $final2 = false)
    {
        $this->orig = $orig ? $orig : [];
        $this->final1 = $final1 ? $final1 : [];
        $this->final2 = $final2 ? $final2 : [];
    }

    public function getOrig()
    {
        return $this->orig;
    }
    public function getFinal1()
    {
        return $this->final1;
    }

    public function getFinal2()
    {
        return $this->final2;
    }

    public function norig(): int
    {
        return $this->orig ? count($this->orig) : 0;
    }

    public function merged()
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

    public function isConflict()
    {
        return $this->merged() === false;
    }
}
