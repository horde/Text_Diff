<?php

namespace Horde\Text\Diff\Op;

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
class Change extends Base
{
    public function __construct($orig, $final)
    {
        $this->orig = $orig;
        $this->final = $final;
    }

    public function reverse()
    {
        return new Change($this->final, $this->orig);
    }
}
