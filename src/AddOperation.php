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
class AddOperation extends BaseOperation
{
    /**
     * Constructor
     * 
     * @param array<string> $lines 
     * @return void 
     */
    public function __construct(array $lines)
    {
        $this->final = $lines;
        $this->orig = false;
    }

    public function reverse(): OperationInterface
    {
        return new DeleteOperation($this->final);
    }
}
