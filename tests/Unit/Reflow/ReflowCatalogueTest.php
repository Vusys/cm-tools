<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Reflow;

use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vusys\CmTools\Reflow\Unwrapper;
use Vusys\CmTools\Reflow\Wrapper;
use Vusys\CmTools\Support\DocumentParser;
use Vusys\CmTools\Tests\Support\ReflowFixture;

/**
 * Runs every case in `tests/Fixtures/Reflow` (see {@see ReflowFixture} for the file format).
 *
 * Each case is checked three ways, because "the output is what I typed into the fixture" alone
 * would only pin the behaviour, not show it is right:
 *
 * 1. the output matches the expectation byte-for-byte;
 * 2. reflowing that output again is a no-op, so the tool is a fixed point on its own output
 *    and a document doesn't drift each time it's run;
 * 3. the input and the output render to identical HTML, so the reflow didn't change what the
 *    document means — the property every reflow bug so far has broken.
 */
final class ReflowCatalogueTest extends TestCase
{
    /**
     * @return iterable<string, array{0: ReflowFixture, 1: int, 2: string}>
     */
    public static function wrapCases(): iterable
    {
        foreach (ReflowFixture::catalogue() as $fixture) {
            foreach ($fixture->wrapExpectations as $width => $expected) {
                yield "{$fixture->name} (wrap at {$width})" => [$fixture, $width, $expected];
            }
        }
    }

    /**
     * @return iterable<string, array{0: ReflowFixture, 1: string}>
     */
    public static function unwrapCases(): iterable
    {
        foreach (ReflowFixture::catalogue() as $fixture) {
            if ($fixture->unwrapExpectation !== null) {
                yield "{$fixture->name} (unwrap)" => [$fixture, $fixture->unwrapExpectation];
            }
        }
    }

    #[DataProvider('wrapCases')]
    public function test_wrapping_matches_the_catalogue(ReflowFixture $fixture, int $width, string $expected): void
    {
        $wrapper = new Wrapper;
        $actual = $wrapper->wrap($fixture->input, $width);

        $this->assertSame($expected, $actual, "Wrapping '{$fixture->name}' at width {$width} produced unexpected output.");
        $this->assertSame($expected, $wrapper->wrap($expected, $width), "Wrapping '{$fixture->name}' at width {$width} is not idempotent — a second pass changes the output again.");
        $this->assertMeaningIsPreserved($fixture, $actual, "wrap at width {$width}");
    }

    #[DataProvider('unwrapCases')]
    public function test_unwrapping_matches_the_catalogue(ReflowFixture $fixture, string $expected): void
    {
        $unwrapper = new Unwrapper;
        $actual = $unwrapper->unwrap($fixture->input);

        $this->assertSame($expected, $actual, "Unwrapping '{$fixture->name}' produced unexpected output.");
        $this->assertSame($expected, $unwrapper->unwrap($expected), "Unwrapping '{$fixture->name}' is not idempotent — a second pass changes the output again.");
        $this->assertMeaningIsPreserved($fixture, $actual, 'unwrap');
    }

    /**
     * Reflowing is only ever allowed to change the *source* — never the document. Where a
     * fixture declares an intentional exception, hold it to that too: a `@@@ html-drift`
     * marker that no longer describes real drift is stale, and would quietly disable this
     * check for a case that has since been fixed.
     */
    private function assertMeaningIsPreserved(ReflowFixture $fixture, string $output, string $operation): void
    {
        $before = self::html($fixture->input);
        $after = self::html($output);

        if ($fixture->htmlDriftReason === null) {
            $this->assertSame($before, $after, "Fixture '{$fixture->name}' changed meaning under {$operation}. If that is intentional, declare it with a '@@@ html-drift' section.");

            return;
        }

        $this->assertNotSame($before, $after, "Fixture '{$fixture->name}' declares html-drift ({$fixture->htmlDriftReason}) but {$operation} preserves the HTML exactly — drop the marker.");
    }

    /**
     * Renders to HTML, then collapses whitespace runs *outside* `<pre>`/`<code>` — a soft line
     * break renders as a literal newline, so re-wrapping a paragraph always moves that
     * whitespace around, and comparing raw HTML would flag every single case. Whitespace runs
     * are not significant in HTML anywhere else, but they are inside code, so those spans are
     * held out of the collapse and compared byte-for-byte.
     */
    private static function html(string $markdown): string
    {
        $html = new MarkdownConverter(DocumentParser::environment())->convert($markdown)->getContent();
        $parts = preg_split('#(<pre\b.*?</pre>|<code\b.*?</code>)#si', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $html;
        }

        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $parts[$i] = (string) preg_replace('/\s+/', ' ', $part);
            }
        }

        return trim(implode('', $parts));
    }
}
