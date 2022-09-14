<?php

declare(strict_types=1);
/**
 * Copyright 2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did
 * not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Text_Diff
 */

namespace Horde\Text\Diff;

use BadMethodCallException;
use Horde_Cli;

/**
 * "Unified" diff renderer with output coloring.
 *
 * @author    Jan Schneider <jan@horde.org>
 * @category  Horde
 * @copyright 2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL
 * @package   Text_Diff
 */
class UnifiedColoredRenderer extends UnifiedRenderer
{
    /**
     * CLI handler.
     *
     * Contrary to the name, it supports color highlighting for HTML too.
     *
     * @var Horde_Cli
     */
    protected $_cli;

    /**
     * Constructor.
     */
    public function __construct($params = [])
    {
        if (!isset($params['cli'])) {
            throw new BadMethodCallException('CLI handler is missing');
        }
        parent::__construct($params);
        $this->_cli = $params['cli'];
    }

    protected function _blockHeader(int $xbeg, int $xlen, int $ybeg, int $ylen): string
    {
        return $this->_cli->color(
            'lightmagenta',
            parent::_blockHeader($xbeg, $xlen, $ybeg, $ylen)
        );
    }

    protected function _added(array $lines = []): string
    {
        return $this->_cli->color(
            'lightgreen',
            parent::_added($lines)
        );
    }

    protected function _deleted(array $lines = []): string
    {
        return $this->_cli->color(
            'lightred',
            parent::_deleted($lines)
        );
    }
}
