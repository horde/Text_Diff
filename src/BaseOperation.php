<?php

declare(strict_types=1);

namespace Horde\Text\Diff;

/**
 * The original PHP version of this code was written by Geoffrey T. Dairiki
 * <dairiki@dairiki.org>, and is used/adapted with his permission.
 *
 * Copyright 2004 Geoffrey T. Dairiki <dairiki@dairiki.org>
 * Copyright 2004-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 */
abstract class BaseOperation implements OperationInterface
{
    /**
     * These will turn non-public at some point
     * 
     * @var mixed
     */
    public $orig;
    public $final;

    abstract public function reverse(): OperationInterface;

    public function norig(): int
    {
        return $this->orig ? count($this->orig) : 0;
    }

    public function getOrig()
    {
        return $this->orig;
    }

    public function getFinal()
    {
        return $this->final;
    }

    public function nfinal(): int
    {
        return $this->final ? count($this->final) : 0;
    }
}
