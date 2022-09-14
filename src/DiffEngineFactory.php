<?php

declare(strict_types=1);

namespace Horde\Text\Diff;

/**
 * Load a Diff Engine.
 */
class DiffEngineFactory
{
    /**
     * Shortcut constructor, internally creating the Engine instance.
     *
     * Default is Auto, meaning it will use XDiffEngine if available, otherwise resort to NativeEngine 
     * If you really care about what engine provides the OperationList, implement your own 
     * 
     * Use this to create
     * - NativeEngine
     * - XDiffEngine
     * - ShellEngine
     * - "Auto": The most appropriate engine to deal with two arrays of file lines
     * - Explicitly any other engine that initializes from two arrays of lines
     * 
     * @return DiffEngineInterface 
     */
    public static function fromFileLineArrays(
        array $fromLines = [],
        array $toLines = [],
        string $engineClass = 'auto',
        array $engineParams = [];
    ): DiffEngineInterface
    {
        $class = 'Horde\Text\Diff\\';
        if ($engineClass == 'auto') {
            $class .= extension_loaded('xdiff') ? XdiffEngine::class : NativeEngine::class;
        } elseif (strpos($engineClass, '_') === false && strpos($engineClass, '\\') === false) {
            $class .= Horde_String::ucfirst(basename($engineClass));
        } else {
            $class = $engineClass;
        }
        $engine = new $class($fromLines, $toLines);
        return $engine;
    }

   /**
    * Shortcut constructor, internally creating the Engine instance.
    *
    * Default is Auto, meaning it will use XDiffEngine if available, otherwise resort to NativeEngine 
    * If you really care about what engine provides the OperationList, implement your own 
    * 
    * Use this to create
    * - StringEngine
    * - "Auto": The most appropriate engine to deal with a single string diff source
    * - Explicitly any other engine that initializes from a single string diff source
    * 
    * @return DiffEngineInterface 
    */
    public static function fromString(
        string $diff,
        string $engineClass = 'auto',
        $engineParams = ['mode' => 'autodetect']
    ): DiffEngineInterface
    {
        if ($engineClass == 'auto') {
            $engineClass = StringEngine::class;
        }
        return new $engineClass($diff, $engineParams['mode']);
    }
}
