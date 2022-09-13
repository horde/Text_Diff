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
class ThreeWayCopyOperation extends ThreeWayBaseOperation
{
    public function __construct(array $lines = [])
    {
        $this->orig = $lines ? $lines : [];
        $this->final1 = &$this->orig;
        $this->final2 = &$this->orig;
    }

    public function merged()
    {
        return $this->orig;
    }

    public function isConflict()
    {
        return false;
    }
}
