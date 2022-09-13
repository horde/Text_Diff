<?php

declare(strict_types=1);

namespace Horde\Text\Diff;

use Countable;
use Iterator;
use IteratorAggregate;

/**
 * Interface for Diff Engines
 */
class OperationList implements IteratorAggregate, Countable
{
    private array $ops = [];
    public function __construct(OperationInterface ...$ops)
    {
        $this->ops = $ops;        
    }

    public function getIterator(): Iterator
    {
        yield from $this->ops;
    }

    public function toArray()
    {
        return $this->ops;
    }
    public function count(): int
    {
        return count($this->ops);
    }
}
