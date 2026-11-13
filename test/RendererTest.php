<?php

/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/gpl GPL
 * @category   Horde
 * @package    Text_Diff
 * @subpackage UnitTests
 */

namespace Horde\Text\Diff\Test;

use Horde\Text\Diff\ChangeOperation;
use Horde\Text\Diff\ContextRenderer;
use Horde\Text\Diff\CopyOperation;
use Horde\Text\Diff\Diff;
use Horde\Text\Diff\InlineRenderer;
use Horde\Text\Diff\NativeEngine;
use Horde\Text\Diff\Renderer;
use Horde\Text\Diff\StringEngine;
use Horde\Text\Diff\UnifiedRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @requires PHP >= 8.1
 */
#[CoversClass(ContextRenderer::class)]
#[CoversClass(InlineRenderer::class)]
#[CoversClass(Renderer::class)]
#[CoversClass(StringEngine::class)]
#[CoversClass(UnifiedRenderer::class)]
class RendererTest extends TestCase
{
    protected $_lines = [];
    protected $fixtureDir = '';

    public function setUp(): void
    {
        $this->fixtureDir = dirname(__FILE__, 1) . '/fixtures/';
        for ($i = 1; $i <= 8; $i++) {
            $this->_lines[$i] = file($this->fixtureDir . $i . '.txt');
        }
    }

    public function testContextRenderer()
    {
        $renderer = new ContextRenderer();

        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], NativeEngine::class);
        $patch = <<<END_OF_PATCH
            ***************
            *** 1,3 ****
              This line is the same.
            ! This line is different in 1.txt
              This line is the same.
            --- 1,3 ----
              This line is the same.
            ! This line is different in 2.txt
              This line is the same.

            END_OF_PATCH;
        $this->assertEquals($patch, $renderer->render($diff));

        $diff = Diff::fromFileLineArrays($this->_lines[5], $this->_lines[6], NativeEngine::class);
        $patch = <<<END_OF_PATCH
            ***************
            *** 1,5 ****
              This is a test.
              Adding random text to simulate files.
              Various Content.
            ! More Content.
            ! Testing diff and renderer.
            --- 1,7 ----
              This is a test.
              Adding random text to simulate files.
            + Inserting a line.
              Various Content.
            ! Replacing content.
            ! Testing similarities and renderer.
            ! Append content.

            END_OF_PATCH;
        $this->assertEquals($patch, $renderer->render($diff));
    }

    public function testInlineRenderer()
    {
        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], NativeEngine::class);

        $renderer = new InlineRenderer(['split_characters' => true]);
        $patch = <<<END_OF_PATCH
            This line is the same.
            This line is different in <del>1</del><ins>2</ins>.txt
            This line is the same.

            END_OF_PATCH;
        $this->assertEquals($patch, $renderer->render($diff));

        $renderer = new InlineRenderer();
        $patch = <<<END_OF_PATCH
            This line is the same.
            This line is different in <del>1.txt</del><ins>2.txt</ins>
            This line is the same.

            END_OF_PATCH;
        $this->assertEquals($patch, $renderer->render($diff));

        $diff = Diff::fromFileLineArrays($this->_lines[7], $this->_lines[8], NativeEngine::class);
        $patch = <<<END_OF_PATCH
            This is a test.
            Adding random text to simulate files.
            <ins>Inserting a line.</ins>
            Various Content.
            <del>More Content.</del><ins>Replacing content.</ins>
            Testing <del>diff</del><ins>similarities</ins> and renderer.<ins>
            Append content.</ins>

            END_OF_PATCH;
        $this->assertEquals($patch, $renderer->render($diff));
    }

    public function testUnifiedRenderer()
    {
        $renderer = new UnifiedRenderer();

        $diff = Diff::fromFileLineArrays($this->_lines[1], $this->_lines[2], NativeEngine::class);
        $patch = <<<END_OF_PATCH
            @@ -1,3 +1,3 @@
             This line is the same.
            -This line is different in 1.txt
            +This line is different in 2.txt
             This line is the same.

            END_OF_PATCH;
        $this->assertEquals($patch, $renderer->render($diff));

        $diff = Diff::fromFileLineArrays($this->_lines[5], $this->_lines[6], NativeEngine::class);
        $patch = <<<END_OF_PATCH
            @@ -1,5 +1,7 @@
             This is a test.
             Adding random text to simulate files.
            +Inserting a line.
             Various Content.
            -More Content.
            -Testing diff and renderer.
            +Replacing content.
            +Testing similarities and renderer.
            +Append content.

            END_OF_PATCH;
        $this->assertEquals($patch, $renderer->render($diff));
    }

    public function testPearBug4879()
    {
        /* inline renderer hangs on numbers in input string */
        $test = [['Common text',
            'Bob had 1 apple, Alice had 2.',
            'Bon appetit!'],
            ['Common text',
                'Bob had 10 apples, Alice had 1.',
                'Bon appetit!']];
        $patch = <<<END_OF_PATCH
            Common text
            Bob had <del>1 apple,</del><ins>10 apples,</ins> Alice had <del>2.</del><ins>1.</ins>
            Bon appetit!

            END_OF_PATCH;

        $diff = Diff::fromFileLineArrays($test[0], $test[1], NativeEngine::class);
        $renderer = new InlineRenderer();
        $this->assertEquals($patch, $renderer->render($diff));
    }

    public function testPearBug4982()
    {
        /* Fixed: wrong line breaks with inline renderer */
        $test = [['This line is different in 1.txt'],
            ['This is new !!',
                'This line is different in 2.txt']];
        $patch = <<<END_OF_PATCH
            <ins>This is new !!</ins>
            This line is different in <del>1.txt</del><ins>2.txt</ins>

            END_OF_PATCH;

        $diff = Diff::fromFileLineArrays($test[0], $test[1], NativeEngine::class);
        $renderer = new InlineRenderer();
        $this->assertEquals($patch, $renderer->render($diff));
    }

    public function testPearBug6251()
    {
        /* too much trailing context */
        $oldtext = <<<EOT

            Original Text



            ss
            ttt
            EOT;

        $newtext = <<<EOT

            Modified Text



            ss
            ttt
            EOT;

        $patch = "@@ -1,5 +1,5 @@\n \n-Original Text\n+Modified Text\n \n \n \n";

        $test = [explode("\n", $oldtext), explode("\n", $newtext)];
        $diff = Diff::fromFileLineArrays($test[0], $test[1], NativeEngine::class);
        $renderer = new UnifiedRenderer(['leading_context_lines' => 3, 'trailing_context_lines' => 3]);
        $this->assertEquals($patch, $renderer->render($diff));
    }

    public function testPearBug6428()
    {
        /* problem with single digits after space */
        $test = [['Line 1',  'Another line'],
            ['Line  1', 'Another line']];
        $patch = <<<END_OF_PATCH
            Line <del>1</del><ins> 1</ins>
            Another line

            END_OF_PATCH;

        $diff = Diff::fromFileLineArrays($test[0], $test[1], NativeEngine::class);
        $renderer = new InlineRenderer();
        $this->assertEquals($patch, $renderer->render($diff));
    }

    public function testPearBug7839()
    {
        $oldtext = <<<EOT
            This is line 1.
            This is line 2.
            This is line 3.
            This is line 4.
            This is line 5.
            This is line 6.
            This is line 7.
            This is line 8.
            This is line 9.
            EOT;

        $newtext = <<<EOT
            This is line 1.
            This was line 2.
            This is line 3.
            This is line 5.
            This was line 6.
            This was line 7.
            This was line 8.
            This is line 9.
            This is line 10.
            EOT;

        $patch = <<<END_OF_PATCH
            2c2
            < This is line 2.
            ---
            > This was line 2.
            4d3
            < This is line 4.
            6,8c5,7
            < This is line 6.
            < This is line 7.
            < This is line 8.
            ---
            > This was line 6.
            > This was line 7.
            > This was line 8.
            9a9
            > This is line 10.

            END_OF_PATCH;

        $test = [explode("\n", $oldtext), explode("\n", $newtext)];
        $diff = Diff::fromFileLineArrays($test[0], $test[1], NativeEngine::class);
        $renderer = new Renderer();
        $this->assertEquals($patch, $renderer->render($diff));
    }

    public function testPearBug12710()
    {
        /* Previously caused infinite recursion due to wrong order of operations in NativeEngine */
        $a = <<<QQ
            <li>The tax credit amounts to 30% of the cost of the system, with a
            maximum of 2,000. This credit is separate from the 500 home improvement
            credit.</li>
            <h3>Fuel Cells<a
            href="12341234213421341234123412341234123421341234213412342134213423"
            class="anchor" title="Link to this section"><br />
            <li>Your fuel 123456789</li>
            QQ;

        $b = <<<QQ
            <li> of gas emissions by 2050</li>
            <li>Raise car fuel economy to 50 mpg by 2017</li>
            <li>Increase access to mass transit systems</li>
            QQ;

        $diff = Diff::fromFileLineArrays(explode("\n", $a), explode("\n", $b), NativeEngine::class);
        $renderer = new InlineRenderer();
        $result = $renderer->render($diff);

        // Should complete without hanging or crashing
        $this->assertIsString($result);
        $this->assertStringContainsString('<del>', $result);
        $this->assertStringContainsString('<ins>', $result);
    }

    public function testGithubPullRequest86()
    {
        $a = <<<EOA
            One
            Two
            EOA;

        $b = <<<EOB
            Ones
            Twos
            EOB;
        $patch = <<<EOPATCH
            One<ins>s</ins>
            Two<ins>s</ins>

            EOPATCH;

        $diff = Diff::fromFileLineArrays(explode("\n", $a), explode("\n", $b), NativeEngine::class);
        $renderer = new InlineRenderer(['split_characters' => true]);
        $this->assertEquals($patch, $renderer->render($diff));
    }

    // -------------------------------------------------------------------------
    // Unified diff output format edge cases
    // -------------------------------------------------------------------------

    /**
     * POSIX rule: when a hunk spans exactly 1 line, the ",count" suffix is omitted.
     * UnifiedRenderer::_blockHeader checks ($xlen != 1) before appending ",$xlen".
     * For a single-line change with no context, both sides have 1 line. "@@ -1 +1 @@".
     * Confirmed by test/fixtures/unified2.patch which contains "@@ -1 +1 @@".
     *
     * @see src/UnifiedRenderer.php _blockHeader()
     */
    public function testUnifiedRendererSingleLineHunk(): void
    {
        $renderer = new UnifiedRenderer(['leading_context_lines' => 0, 'trailing_context_lines' => 0]);
        $from = ["old line\n"];
        $to   = ["new line\n"];

        $diff   = Diff::fromFileLineArrays($from, $to, NativeEngine::class);
        $output = $renderer->render($diff);

        // The hunk header must not carry ",1" on either side
        $this->assertStringContainsString('@@ -1 +1 @@', $output);
        $this->assertStringNotContainsString('@@ -1,1', $output);
        $this->assertStringNotContainsString('+1,1 @@', $output);
        $this->assertStringContainsString('-old line', $output);
        $this->assertStringContainsString('+new line', $output);
    }

    /**
     * Pure insertion (original side has 0 lines).
     * _blockHeader(xbeg=1, xlen=0, ybeg=1, ylen=3). "@@ -1,0 +1,3 @@"
     * The ",0" suffix on the origin side marks an empty range.
     * Output must contain only "+" lines, no "-" lines.
     */
    public function testUnifiedRendererPureInsertion(): void
    {
        $renderer = new UnifiedRenderer(['leading_context_lines' => 0, 'trailing_context_lines' => 0]);
        $from = [];
        $to   = ["This line is the same.\n", "This line is different in 2.txt\n", "This line is the same.\n"];

        $diff   = Diff::fromFileLineArrays($from, $to, NativeEngine::class);
        $output = $renderer->render($diff);

        $this->assertStringContainsString(',0', $output, 'Hunk header must carry ",0" for the empty origin side');
        $this->assertStringContainsString('+This line is the same.', $output);
        $this->assertStringNotContainsString("\n-", $output, 'Pure insertion must contain no deleted lines');
    }

    /**
     * Pure deletion (revised side has 0 lines).
     * _blockHeader(xbeg=1, xlen=3, ybeg=1, ylen=0). "@@ -1,3 +1,0 @@"
     * Output must contain only "-" lines, no "+" lines.
     */
    public function testUnifiedRendererPureDeletion(): void
    {
        $renderer = new UnifiedRenderer(['leading_context_lines' => 0, 'trailing_context_lines' => 0]);
        $from = ["This line is the same.\n", "This line is different in 1.txt\n", "This line is the same.\n"];
        $to   = [];

        $diff   = Diff::fromFileLineArrays($from, $to, NativeEngine::class);
        $output = $renderer->render($diff);

        $this->assertStringContainsString(',0', $output, 'Hunk header must carry ",0" for the empty revised side');
        $this->assertStringContainsString('-This line is the same.', $output);
        $this->assertStringNotContainsString("\n+", $output, 'Pure deletion must contain no inserted lines');
    }

    // -------------------------------------------------------------------------
    // Context diff output format edge cases
    // -------------------------------------------------------------------------

    /**
     * POSIX: single-line hunk range omits ",1".
     * ContextRenderer::_blockHeader appends ",$xlen" only when $xlen != 1.
     * For a 1-line file diffed against a 1-line file: "*** 1 ****" / "--- 1 ----".
     *
     * @see src/ContextRenderer.php _blockHeader()
     */
    public function testContextRendererSingleLineHunk(): void
    {
        $renderer = new ContextRenderer(['leading_context_lines' => 0, 'trailing_context_lines' => 0]);
        $from = ["old\n"];
        $to   = ["new\n"];

        $diff   = Diff::fromFileLineArrays($from, $to, NativeEngine::class);
        $output = $renderer->render($diff);

        $this->assertStringContainsString(
            '*** 1 ****',
            $output,
            'Single-line hunk must omit ",1" on the original-side range'
        );
        $this->assertStringContainsString(
            '--- 1 ----',
            $output,
            'Single-line hunk must omit ",1" on the revised-side range'
        );
        $this->assertStringNotContainsString(
            ',1',
            $output,
            'No ",1" count suffix should appear for a single-line hunk'
        );
    }

    /**
     * All-deletions: revised side is empty.
     * _blockHeader(1, 3, 1, 0) sets the second-block header to "--- 1,0 ----".
     * The "***" section has the deleted lines; the "---" section is present but empty.
     */
    public function testContextRendererAllDeletions(): void
    {
        $renderer = new ContextRenderer(['leading_context_lines' => 0, 'trailing_context_lines' => 0]);
        $from = ["line one\n", "line two\n", "line three\n"];
        $to   = [];

        $diff   = Diff::fromFileLineArrays($from, $to, NativeEngine::class);
        $output = $renderer->render($diff);

        // Origin section must be present and contain deleted lines
        $this->assertStringContainsString('****', $output);
        $this->assertStringContainsString('- line one', $output);
        $this->assertStringContainsString('- line three', $output);

        // Revised section must be present with a ",0" range but no added content
        $this->assertStringContainsString('----', $output);
        $this->assertStringContainsString(
            ',0',
            $output,
            'Revised-side range must carry ",0" when revised file is empty'
        );
        $this->assertStringNotContainsString(
            '+ ',
            $output,
            'All-deletions diff must contain no added lines in output'
        );
    }

    // -------------------------------------------------------------------------
    // Unified diff parsing (StringEngine) edge cases
    // -------------------------------------------------------------------------

    /**
     * Lines starting with "Binary files ... differ" begin with 'B', which hits
     * the default branch in parseUnifiedDiff(). silently skipped (no op created).
     * The text hunks before/after the binary marker must still parse correctly.
     */
    public function testStringEngineSkipsBinaryMarkerLines(): void
    {
        $patch = "--- a/text.txt\n"
               . "+++ b/text.txt\n"
               . "@@ -1,3 +1,3 @@\n"
               . " This line is the same.\n"
               . "-This line is different in 1.txt\n"
               . "+This line is different in 2.txt\n"
               . " This line is the same.\n"
               . "Binary files a/image.png and b/image.png differ\n";

        $diff = Diff::fromString($patch, StringEngine::class);

        // Binary marker produces no op; the text hunk still gives the canonical 3-op structure
        $edits = $diff->getDiff()->toArray();
        $this->assertCount(
            3,
            $edits,
            'Binary marker line must be skipped, leaving the 3-op text hunk'
        );
        $this->assertInstanceOf(CopyOperation::class, $edits[0]);
        $this->assertInstanceOf(ChangeOperation::class, $edits[1]);
        $this->assertInstanceOf(CopyOperation::class, $edits[2]);
        $this->assertSame('This line is the same.', $edits[0]->orig[0]);
        $this->assertSame('This line is different in 1.txt', $edits[1]->orig[0]);
        $this->assertSame('This line is different in 2.txt', $edits[1]->final[0]);
        $this->assertSame('This line is the same.', $edits[2]->orig[0]);
    }

    /**
     * "\ No newline at end of file" lines start with '\', which also hits the
     * default branch. silently skipped.  The change op's content must be unaffected.
     */
    public function testStringEngineSkipsNoNewlineMarker(): void
    {
        $patch = "--- a/file.txt\n"
               . "+++ b/file.txt\n"
               . "@@ -1,3 +1,3 @@\n"
               . " This line is the same.\n"
               . "-This line is different in 1.txt\n"
               . "+This line is different in 2.txt\n"
               . " This line is the same.\n"
               . "\\ No newline at end of file\n";

        $diff  = Diff::fromString($patch, StringEngine::class);
        $edits = $diff->getDiff()->toArray();

        $this->assertCount(3, $edits);
        $this->assertInstanceOf(ChangeOperation::class, $edits[1]);

        // The "\" line must NOT have been appended to the last change's content
        $this->assertSame('This line is different in 2.txt', $edits[1]->final[0]);
    }

    /**
     * A patch with 3 separate "@@ ... @@" hunks must produce 3 independent ops.
     * Each "@@ ... @@" line starts with '@'. default branch (skip).
     * The "-"/"+" pairs in each hunk produce ChangeOperations.
     */
    public function testStringEngineMultiHunkPatch(): void
    {
        $patch = "--- original\n"
               . "+++ revised\n"
               . "@@ -1 +1 @@\n"
               . "-line A\n"
               . "+line X\n"
               . "@@ -5 +5 @@\n"
               . "-line B\n"
               . "+line Y\n"
               . "@@ -9 +9 @@\n"
               . "-line C\n"
               . "+line Z\n";

        $diff  = Diff::fromString($patch, StringEngine::class);
        $edits = $diff->getDiff()->toArray();

        $this->assertCount(
            3,
            $edits,
            'Three "@@ ... @@" hunks should yield exactly 3 ops'
        );

        $this->assertInstanceOf(ChangeOperation::class, $edits[0]);
        $this->assertInstanceOf(ChangeOperation::class, $edits[1]);
        $this->assertInstanceOf(ChangeOperation::class, $edits[2]);

        $this->assertSame('line A', $edits[0]->orig[0]);
        $this->assertSame('line X', $edits[0]->final[0]);
        $this->assertSame('line B', $edits[1]->orig[0]);
        $this->assertSame('line Y', $edits[1]->final[0]);
        $this->assertSame('line C', $edits[2]->orig[0]);
        $this->assertSame('line Z', $edits[2]->final[0]);
    }

    // -------------------------------------------------------------------------
    // Inline HTML renderer boundary cases
    // -------------------------------------------------------------------------

    /**
     * Identical input. All ops are CopyOperation, so the diff is empty and the
     * InlineRenderer emits no block at all (GNU diff of identical files prints
     * nothing). No <del>/<ins> tags and no content are produced.
     */
    public function testInlineRendererIdenticalLines(): void
    {
        $lines = ["This line is the same.\n", "Also the same.\n"];
        $diff  = Diff::fromFileLineArrays($lines, $lines, NativeEngine::class);

        $this->assertTrue(
            $diff->isEmpty(),
            'Identical input must produce an empty diff'
        );

        $renderer = new InlineRenderer();
        $output   = $renderer->render($diff);

        $this->assertStringNotContainsString(
            '<del',
            $output,
            'Identical input must produce no <del> tags'
        );
        $this->assertStringNotContainsString(
            '<ins',
            $output,
            'Identical input must produce no <ins> tags'
        );
        $this->assertSame(
            '',
            $output,
            'An empty diff must render as an empty string (no hunks)'
        );
    }

    /**
     * All-inserted: original is empty.  Every content line must be wrapped in <ins>.
     * No <del> tag should be present.
     */
    public function testInlineRendererAllInserted(): void
    {
        $to   = ["This line is the same.\n", "This line is different in 2.txt\n", "This line is the same.\n"];
        $diff = Diff::fromFileLineArrays([], $to, NativeEngine::class);

        $renderer = new InlineRenderer();
        $output   = $renderer->render($diff);

        $this->assertStringContainsString(
            '<ins>',
            $output,
            'All-inserted diff must produce <ins> tags'
        );
        $this->assertStringNotContainsString(
            '<del>',
            $output,
            'All-inserted diff must not produce <del> tags'
        );
    }

    /**
     * All deleted, revised is empty.  Every content line must be wrapped in <del>.
     * No <ins> tag should be present.
     */
    public function testInlineRendererAllDeleted(): void
    {
        $from = ["This line is the same.\n", "This line is different in 1.txt\n", "This line is the same.\n"];
        $diff = Diff::fromFileLineArrays($from, [], NativeEngine::class);

        $renderer = new InlineRenderer();
        $output   = $renderer->render($diff);

        $this->assertStringContainsString(
            '<del>',
            $output,
            'All-deleted diff must produce <del> tags'
        );
        $this->assertStringNotContainsString(
            '<ins>',
            $output,
            'All-deleted diff must not produce <ins> tags'
        );
    }

    /**
     * HTML special characters in unchanged (context) lines.
     *
     * InlineRenderer escapes content via htmlspecialchars() in _encode(), so
     * "<", ">" and "&" in context lines are emitted in their escaped form.
     * This test documents and locks down that (safe) behaviour.
     */
    public function testInlineRendererEscapesHtmlSpecialCharsInContext(): void
    {
        // First line is unchanged context; second line changes one word
        $from = ["<b>same &amp; stable</b>\n", "changed word\n"];
        $to   = ["<b>same &amp; stable</b>\n", "new word\n"];

        $diff     = Diff::fromFileLineArrays($from, $to, NativeEngine::class);
        $renderer = new InlineRenderer();
        $output   = $renderer->render($diff);

        // Context line must appear HTML-escaped (htmlspecialchars):
        // "<" -> "&lt;", ">" -> "&gt;", "&" -> "&amp;"
        $this->assertStringContainsString(
            '&lt;b&gt;same &amp;amp; stable&lt;/b&gt;',
            $output,
            'HTML special chars in context lines must be escaped via htmlspecialchars()'
        );
        $this->assertStringNotContainsString(
            '<b>same',
            $output,
            'The raw, unescaped <b> markup must not leak into the output'
        );

        // The changed word should be wrapped in del/ins
        $this->assertStringContainsString('<del>', $output);
        $this->assertStringContainsString('<ins>', $output);
    }
}
