<?php

/**
 * @author     Ralf Lang
 * @license    http://www.horde.org/licenses/gpl GPL
 * @category   Horde
 * @package    Text_Diff
 * @subpackage UnitTests
 *
 * UnifiedColoredRenderer
 *
 * UnifiedColoredRenderer wraps added lines in $cli->color('lightgreen', ...),
 * deleted lines in $cli->color('lightred', ...) and block headers in
 * $cli->color('lightmagenta', ...).  Context (unchanged) lines are NOT
 * color-wrapped. They pass through the parent UnifiedRenderer unchanged.
 *
 * The renderer requires a Horde/Cli instance via $params['cli'].
 * Tests use an anonymous-class stand-in that wraps text in predictable
 * "[COLOR]text[/COLOR]" markers instead of real ANSI/HTML codes
 **/

namespace Horde\Text\Diff\Test;

use BadMethodCallException;
use Horde\Text\Diff\Diff;
use Horde\Text\Diff\NativeEngine;
use Horde\Text\Diff\UnifiedColoredRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @requires PHP >= 8.1
 */
#[CoversClass(UnifiedColoredRenderer::class)]
class ColoredRendererTest extends TestCase
{
    /** Returns a minimal Horde_Cli stand-in that wraps text in [COLOR]...[/COLOR]. */
    private function mockCli(): object
    {
        return new class {
            public function color(string $name, string $text): string
            {
                return "[{$name}]{$text}[/{$name}]";
            }
        };
    }

    // -------------------------------------------------------------------------
    // Constructor guard
    // -------------------------------------------------------------------------

    /**
     * The constructor must throw BadMethodCallException when the 'cli' key
     * is absent from $params.
     *
     * @see src/UnifiedColoredRenderer.php __construct()
     */
    public function testConstructorThrowsWithoutCliParam(): void
    {
        $this->expectException(BadMethodCallException::class);
        new UnifiedColoredRenderer([]);
    }

    // -------------------------------------------------------------------------
    // Color wrapping for added / deleted / header lines
    // -------------------------------------------------------------------------

    /**
     * Added lines must be wrapped via $cli->color('lightgreen', ...).
     *
     * Input: pure insertion (from = [], to = ['new line\n']).
     * The "+" content line must be inside "[lightgreen]...[/lightgreen]".
     */
    public function testColoredRendererAddedLinesColor(): void
    {
        $renderer = new UnifiedColoredRenderer(['cli' => $this->mockCli()]);
        $diff     = Diff::fromFileLineArrays([], ["new line\n"], NativeEngine::class);
        $output   = $renderer->render($diff);

        $this->assertStringContainsString(
            '[lightgreen]',
            $output,
            'Added lines must be wrapped in the lightgreen color marker'
        );
        $this->assertStringContainsString('new line', $output);
    }

    /**
     * Deleted lines must be wrapped via $cli->color('lightred', ...).
     *
     * Input: pure deletion (from = ['old line\n'], to = []).
     * The "-" content line must be inside "[lightred]...[/lightred]".
     */
    public function testColoredRendererRemovedLinesColor(): void
    {
        $renderer = new UnifiedColoredRenderer(['cli' => $this->mockCli()]);
        $diff     = Diff::fromFileLineArrays(["old line\n"], [], NativeEngine::class);
        $output   = $renderer->render($diff);

        $this->assertStringContainsString(
            '[lightred]',
            $output,
            'Deleted lines must be wrapped in the lightred color marker'
        );
        $this->assertStringContainsString('old line', $output);
    }

    /**
     * Block headers (@@ ... @@) must be wrapped via $cli->color('lightmagenta', ...).
     *
     * Any diff that produces at least one hunk will have a block header.
     */
    public function testColoredRendererBlockHeaderColor(): void
    {
        $renderer = new UnifiedColoredRenderer(['cli' => $this->mockCli()]);
        $diff     = Diff::fromFileLineArrays(
            ["This line is the same.\n", "This line is different in 1.txt\n", "This line is the same.\n"],
            ["This line is the same.\n", "This line is different in 2.txt\n", "This line is the same.\n"],
            NativeEngine::class
        );
        $output = $renderer->render($diff);

        $this->assertStringContainsString(
            '[lightmagenta]',
            $output,
            'Block headers must be wrapped in the lightmagenta color marker'
        );
        $this->assertStringContainsString(
            '@@',
            $output,
            'The @@ hunk header text must still be present inside the color marker'
        );
    }

    /**
     * Unchanged context lines must NOT be wrapped in any color marker.
     * UnifiedColoredRenderer does not override _context(), so context lines
     * are emitted by the parent UnifiedRenderer as plain text.
     *
     * Input: 1-line context sandwiching the change (3-line files, default context=4).
     * The "This line is the same." lines appear in context must not be colorized.
     */
    public function testColoredRendererContextLinesUncolored(): void
    {
        $renderer = new UnifiedColoredRenderer(['cli' => $this->mockCli()]);
        $from     = ["unchanged\n", "old value\n", "unchanged\n"];
        $to       = ["unchanged\n", "new value\n", "unchanged\n"];

        $diff   = Diff::fromFileLineArrays($from, $to, NativeEngine::class);
        $output = $renderer->render($diff);

        // Check that the context line "unchanged" appears outside any color wrapper
        // by verifying it is not immediately preceded by "]" from a color marker close.
        // A simpler proxy: the raw text appears in the output at all.
        $this->assertStringContainsString(
            'unchanged',
            $output,
            'Context line content must appear in the output'
        );

        // Context lines are prefixed with " " (space) in unified format.
        // They should NOT be wrapped in [lightgreen] or [lightred].
        $this->assertStringNotContainsString('[lightgreen] unchanged', $output);
        $this->assertStringNotContainsString('[lightred] unchanged', $output);
    }
}
